<?php
/**
* @name app/ai.engines/deepseek.php
* настройки для обертки над движком DeepSeek AI
* @version 0.01.001
* OpenAI
* DeekSeek API docs по-русски: https://b.deepseek3.ru/api/
* Точки входа для некоторых LLM
* Deepseek: https://api.deepseek.com/v3
* OpenRouter: https://openrouter.ai/api/v1/chat/completions
* modified 2025-11-18
**/
namespace Libs\aiengines;

class DeepSeek {
    private $config = [
      'apiKey' => 'YOUR-TOKEN-HERE',
      'baseUrl' => 'https://api.deepseek.com/v3',
      'maxTokens' => 1024,
      'Model' => 'deepseek-chat-33b',
      'Temperature' => 0.7,
    ];
    private $context = '';
    private $openAiHelper = NULL;
    public function __construct() {

        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_apikey')) $this->config['apiKey'] = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_baseurl')) $this->config['baseUrl'] = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_temperature')) $this->config['Temperature'] = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_model')) $this->config['Model'] = $cfgVal;
        if($cfgVal = \AppEnv::getConfigValue('ai_deepseek_maxtokens')) $this->config['maxTokens'] = $cfgVal;

        $cfgFile = \AppEnv::getAppFolder('cfg/') . 'openai-deepseek.php';
        if(is_file($cfgFile))
            $this->loadConfig($cfgFile);

        $this->openAiHelper = new \Libs\OpenAI($this->config);
    }
    public function loadConfig($cfgName) {
        $arCfg = include($cfgName);
        if(is_array($arCfg)) $this->config = array_merge($this->config, $arCfg);
    }
    public function setContext($params = '') {
        $this->context = $params;
        return $this;
    }

    public function request($params = '', $arHist = [], $context = '') {
        if(is_string($params)) $request  = $params;
        elseif(is_array($params)) $request = $params['request'] ?? '';

        if(!empty($context)) $this->context = $context;
        $result = $this->openAiHelper->request($request, $arHist, $this->context);
        return $result;
    }
    /**
    * вернет список моделей, доступных для использования
    */
    public static function modelList() {
        $arModels = $this->openAiHelper->modelList();
        return $arModels;
    }
    public static function getEngineInfo() {
        return $this->openAiHelper->getEngineInfo();
    }
}
