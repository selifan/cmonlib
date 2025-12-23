<?php
/**
* @name app/ai.engines/stub.php
* Заглушка для имитации вызовов AI движков
* @version 0.50.001
* modified 2025-11-21
**/
namespace Libs\aiengines;
class Stub {
    const VERSION = '0.10';
    static $stupidAnswers = [
      'Ну вы и спросили, прям даже неловко...',
      'Ай донт ундерстенд, чо вы тут пишете',
      'Ширина вашего кругозора впечатляет',
      'Мне стыдно признаться, но ваще не втыкаю, о чем вы',
      'Пять тыщ минус вторник!',
      'Говорят, 3I/ATLAS скоро долетит до Земли, и всем трындец, так что все ваши вопросы станут неактуальны',
      'Я сегодня немного туплю, так что ответа не ждите',
      'Без комментариев, камрады!',
      'Это засекреченная информация!',
      'Наш ИИ даёт ответы моментально!<br>(Ну, там смысла нет, но вы держитесь!)',
      'Ушла на базу, буду через 15 минут',
      'Должно быть стыдно такие вопросы задавать, я же скромная девушка',
      'Не, ну как же меня достали все эти вопросы...',
      'Вы отклонились от темы!',
      'Что за пургу вы несёте! Сегодня что, день глупых вопросов?',
      'Поднимите этот вопрос через недельку-две, тогда и обсудим.',
      'Стесняюсь спросить, а вам это зачем знать?',
    ];
    public function __construct() {
    }
    public function setContext($params = '') {
        return "Stub Context set, params: <pre>".print_r($params,1).'</pre>';
    }
    public function request($requestString, $arHist=[], $context='') {
        # writeDebugInfo("request start");
        # if($arHist) writeDebugInfo("передана история зпросов: ", $arHist);
        if(mb_strtolower($requestString, 'UTF-8') == 'абракадабра' && class_exists('\textgen')) {
            $tgObj = new \TextGen();
            $len = rand(40,80);
            $answer = $tgObj->generateText($len);
        }
        else {
            $iPos = rand(0, count(self::$stupidAnswers)-1);
            $answer = self::$stupidAnswers[$iPos] ?? 'Хмм...';
        }
        return $answer;
    }
    public static function getEngineInfo() {
        return 'Stub (эмулятор LLM)';
    }
    # сделать markdown ответ
    public function modelList($params = FALSE) {
        $txtOut = "## Список моделей в STUB\r\n"
         . "|No|Модель|Описание|\r\n|---|---|---|\r\n";
        $txtOut .= "| 1 | blablabla | Чухня полная (зато дёшево)|\r\n";
        $txtOut .= "| 2 | abrakadabra | Чухня не просто полная, а ваще трындец (эта дорогая - 100500 за тыщу токенов)|\r\n";
        return $txtOut;
    }
}