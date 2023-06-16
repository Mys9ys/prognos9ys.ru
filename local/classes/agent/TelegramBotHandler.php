<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class TelegramBotHandler
{

    protected $inputMessage;

    protected $userId;

    protected $apiProps = [
        'token' => '6112119458:AAFYVuXISmpw4-34ThSahcT22oFd1GqgAz0',
        'apiUrl' => 'https://api.telegram.org/bot',
        'chat_id' => ''
    ];

    protected $chatIdGroup = -1001801926113;

    protected $apiMethods = [
        'sendText' => '/sendMessage?',
        'sendPost' => 'sendPhoto'
    ];

    protected $arTempMessage = [
        'hello' => 'Пришлите ваш e-mail, который используется для авторизации на сайте prognos9ys.ru',
        'error' => 'Такого e-mail нет в системе',
        'success' => 'Вы успешно подписались на уведомления. Управлять подписками можно в профиле на сайте prognos9ys.ru',
        'error_text' => 'К сожалению вы прислали что-то непонятное нам'
    ];

    public function __construct($message = '')
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->inputMessage = $message;

        if ($this->inputMessage) $this->messageHandler();
    }

    protected function messageHandler()
    {
        if ($this->inputMessage['entities']) {

            if ($this->inputMessage['entities'][0]['type'] === 'bot_command') {
                if ($this->inputMessage['text'] === '/start')
                    $this->sendText('hello', $this->inputMessage['chat']['id']);
            } elseif ($this->inputMessage['entities'][0]['type'] === 'email') {
                $mail = mb_substr($this->inputMessage['text'], $this->inputMessage['entities'][0]['offset'], $this->inputMessage['entities'][0]['length']);
                if($this->findSendMail($mail)){
                    $this->setChatID($this->inputMessage['chat']['id'], $this->inputMessage['chat']['username']);
                    $this->sendText('success', $this->inputMessage['chat']['id']);
                } else {
                    $this->sendText('error', $this->inputMessage['chat']['id']);
                }
            } else {
                $this->sendText('error_text', $this->inputMessage['chat']['id']);
            }

        } else {
            $this->sendText('error_text', $this->inputMessage['chat']['id']);
        }
    }

    protected function findSendMail($mail)
    {
        $dbUser = UserTable::getList(array(
            'select' => array('ID'),
            'filter' => array('=EMAIL' => $mail)
        ))->fetch();

        return $this->userId = $dbUser["ID"];
    }

    protected function setChatID($chatId, $tgNick){
        $user = new CUser;
        $fields = array(
            "UF_CHAT_ID" => $chatId,
            "UF_TG_NICK" => $tgNick,
        );
        return $user->Update($this->userId, $fields);
    }

    protected function sendText($mes, $chatId)
    {
        var_dump($mes);

        $query = http_build_query([
            'chat_id' => $chatId,
            'text' => $this->arTempMessage[$mes],
            'parse_mode' => 'html'
        ]);

        $query = $this->apiProps['apiUrl'] . $this->apiProps['token'] . $this->apiMethods['sendText'] . $query;

        var_dump($query);
        $this->send($query);
    }

    protected function sendPost()
    {

    }

    protected function send($query, $arrayQuery = '')
    {

        $ch = curl_init($query);
        if ($arrayQuery) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
    }


}