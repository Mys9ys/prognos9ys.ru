<?php

use Bitrix\Main\Loader;

class SendMatchReminderMessage
{
    protected $messagesIb;

    protected $arMessages;

    protected $tgChat = '-1001415246108';
    protected $tgToken = '6112119458:AAFYVuXISmpw4-34ThSahcT22oFd1GqgAz0';

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->messagesIb = \CIBlock::GetList([], ['CODE' => 'tgreminder'], false)->Fetch()['ID'] ?: 21;

        $this->getMessageArray();

        if (count($this->arMessages) > 0) {
            $this->sendMessageToTelegramm();
        }
    }

    protected function getMessageArray()
    {
        $arFilter["IBLOCK_ID"] = $this->messagesIb;
        $arFilter["ACTIVE"] = 'Y';

        $now = new DateTime();

        $arFilter[">=DATE_ACTIVE_FROM"] = $now->modify('-' . (5) . ' minutes')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = $now->modify('+' . (5) . ' minutes')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "NAME",
                "PREVIEW_TEXT",
                "PROPERTY_pic",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arMessages[] = $res;
        }
    }

    protected function sendMessageToTelegramm()
    {
        foreach ($this->arMessages as $item) {
            $arrayQuery = array(
                'chat_id' => $this->tgChat,
                'caption' => strip_tags($item['PREVIEW_TEXT']),
                'photo' => curl_file_create('https://prognos9ys.ru/' . CFile::GetPath($item['PROPERTY_PIC_VALUE']), 'image/jpg' , 'img.jpg')
            );

            $this->sendTG($arrayQuery);

            $el = new CIBlockElement;
            $res = $el->Update($item['ID'], Array("ACTIVE"=>"N"));
        }
    }

    protected function sendTG($arrayQuery)
    {
        $ch = curl_init('https://api.telegram.org/bot'. $this->tgToken .'/sendPhoto');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
    }
}