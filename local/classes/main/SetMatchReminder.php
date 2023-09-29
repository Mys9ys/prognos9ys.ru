<?php

use Bitrix\Main\Loader;

class SetMatchReminder
{
    protected $matchesIb;
    protected $messagesIb;
    protected $arEvents;

    protected $arTodayMatches;

    protected $arReminderMessage;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->messagesIb = \CIBlock::GetList([], ['CODE' => 'tgreminder'], false)->Fetch()['ID'] ?: 21;

        $this->arEvents = (new GetPrognosisEvents())->result()['events'];

        $this->getTodayMatches();

        if (count($this->arTodayMatches) > 0) {
            $this->parseArMatches();
        }

        if(count($this->arReminderMessage) > 0){
            $this->saveMessages();
        }

    }

    protected function getTodayMatches()
    {
        $arFilter["IBLOCK_ID"] = $this->matchesIb;
        $arFilter["ACTIVE"] = 'Y';
        $now = new DateTime();

        $now_hours = intval(date('H'));

        $arFilter[">=DATE_ACTIVE_FROM"] = $now->modify('-' . $now_hours . ' hours')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = $now->modify('+24 hours')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "NAME",
                "PROPERTY_number",
                "PROPERTY_events",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arTodayMatches[$res['PROPERTY_EVENTS_VALUE']][$res['ACTIVE_FROM']] = $res;
        }
    }

    protected function parseArMatches()
    {

        foreach ($this->arTodayMatches as $event => $items) {


            $message = 'Через 3 часа начнутся футбольные матчи в рамках события: ' . PHP_EOL;
            $message .= $this->arEvents[$event]['NAME'] . PHP_EOL;

            $mCount = count($items);

            sort($items);
            $first = array_shift($items)['ACTIVE_FROM'];
            $message .= 'Первый начало: ' . date('H:i', strtotime($first) - 60 * 60 * 3) . PHP_EOL;
            $message .= 'Количество: ' . $mCount . PHP_EOL;
            $message .= PHP_EOL;
            $message .= 'prognos9ys.ru' . PHP_EOL;

            $this->arReminderMessage[] = [
                'message' => $message,
                'name' => $this->arEvents[$event]['PREVIEW_TEXT'] . ' ' . date('d.m.y', strtotime($first)) . ' матчей: ' . $mCount,
                'pic' => $this->arEvents[$event]['DETAIL_PICTURE'],
                'start_date' =>  date('d.m.y H:i:s', strtotime($first) - 60 * 60 * 3)
            ];

        }
    }

    protected function saveMessages(){
        foreach ($this->arReminderMessage as $mes){

            $ib = new CIBlockElement;

            $data = [
                "NAME" => $mes['name'],
                "IBLOCK_ID" => $this->messagesIb,
                "DATE_ACTIVE_FROM" => $mes['start_date'],
                "PROPERTY_VALUES" => [110 => $mes['pic']]
            ];

            $ib->Add($data);
        }
    }
}