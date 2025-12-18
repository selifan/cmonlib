<?php
/**
* @name libs/openai.php
* реализация протокола OpenAI работы с LLM
* @version 0.10.001
* DeekSeek API docs по-русски: https://b.deepseek3.ru/api/
* Точки входа для некоторых LLM
* Deepseek: https://api.deepseek.com/v3
* OpenRouter: https://openrouter.ai/api/v1/chat/completions
* modified 2025-11-18 / created 2025-11-18
**/
namespace Libs;

class OpenAI {
    private $providerName = 'OpenRouter';
    private $apiKey = '';
    private $baseUrl = '';
    private $Temperature = 0.7;
    private $maxTokens = 1024;
    private $model = '';
    private $context = '';

    public function __construct($config=FALSE) {
        if(!empty($config)) $this->loadConfig($config);
    }
    public function loadConfig($cfg) {
        $arCfg = [];
        if(is_string($cfg)) {
            if(is_file($cfg))
                $arCfg = include($cfg);
        }
        elseif(is_array($cfg))
            $arCfg = $cfg;

        if(isset($arCfg['name'])) $this->providerName = $arCfg['name'];
        if(isset($arCfg['apiKey'])) $this->apiKey = $arCfg['apiKey'];
        if(isset($arCfg['baseUrl'])) $this->baseUrl = $arCfg['baseUrl'];
        if(isset($arCfg['Temperature'])) $this->Temperature = $arCfg['Temperature'];
        if(isset($arCfg['Model'])) $this->model = $arCfg['Model'];
        if(isset($arCfg['maxTokens'])) $this->maxTokens = $arCfg['maxTokens'];
    }
    public function setContext($context = '') {
        $this->context = $context;
        return $this;
    }
    public function request($request, $arHist = [], $context = '') {

        if(empty($request)) return 'Empty request string!';
        if(!empty($context)) $this->context = $context;

        $headers = [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
          # 'HTTP-Referer' => 'My Site URL',
          # "X-Title": "<YOUR_SITE_NAME>"
        ];
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
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'temperature' => $this->Temperature,
            'max_tokens' => $this->maxTokens,
            # 'presence_penalty' => 0,
            # 'response_format' => ['type'=>'text'],
        ];
        try {
            $response = \Curla::getFromUrl($this->baseUrl,$postFields,30,$headers, TRUE);
            $responseData = json_decode($response, true);
            $result = $responseData['choices'][0]['message']['content'] ?? 'no answer?';
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
    public static function modelList() {
        $headers = [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
        ];
        $url = $this->baseUrl . 'models';
        try {
            $response = \Curla::getFromUrl($url,'',30,$headers, TRUE);
            $responseData = json_decode($response, true);
            $result = $responseData['data'] ?? [];
        }
        catch(Exception $e) { $result = $e->getMessage(); }
        return $result;
    }
    public static function getEngineInfo() {
        return$this->providerName . '/model '. $this->model;
    }

}
