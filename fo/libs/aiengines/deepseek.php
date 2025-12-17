<?php
/**
* @name app/ai.engines/deepseek.php
* настройки для обертки над движком DeepSeek AI
* @version 0.01.001
* DeekSeek API docs по-русски: https://b.deepseek3.ru/api/
* modified 2025-11-13
**/
namespace Libs\aiengines;

class DeepSeek {
    private $apiKey = '';
    private $baseUrl = 'https://api.deepseek.com/v3';
    private $Temperature = 0.7;
    private $maxTokens = 1024;
    private $defaultModel = 'deepseek-chat-33b';
    private $model = 'deepseek-chat-33b';
    private $context = '';
    public function __construct() {
        if(is_file(__DIR__ . "/cfg.deepseek.php"))
            $this->loadConfig(__DIR__ . "/cfg.deepseek.php");
    }
    public function loadConfig($cfgName) {
        $arCfg = include($cfgName);
        if(isset($arCfg['apiKey'])) $this->apiKey = $arCfg['apiKey'];
        if(isset($arCfg['baseUrl'])) $this->baseUrl = $arCfg['baseUrl'];
        if(isset($arCfg['Temperature'])) $this->Temperature = $arCfg['Temperature'];
        if(isset($arCfg['Model'])) $this->model = $arCfg['Model'];
        if(isset($arCfg['maxTokens'])) $this->maxTokens = $arCfg['maxTokens'];
        # get from global app config:
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_apikey')) $this->apiKey = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_baseurl')) $this->baseUrl = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_temperature')) $this->Temperature = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_model')) $this->model = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_maxtokens')) $this->maxTokens = $cfgVal;
    }
    public function setContext($params = '') {
        $this->context = $params;
        return $this;
    }
    public function request($params = '', $arHist = [], $context = '') {
        if(is_string($params)) $request  = $params;
        elseif(is_array($params)) $request = $params['request'] ?? 'Explain quantum computing in simple terms';

        if(!empty($context)) $this->context = $context;

        $headers = [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
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
            'temperature' => $this->Temperature,
            'max_tokens' => $this->maxTokens,
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
        $arModels = ['deepseek-chat-33b'];
        return $arModels;
    }
}
