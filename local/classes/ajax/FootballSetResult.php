<?php

use Bitrix\Main\Loader;

class FootballSetResult
{
    protected $data;
    protected $arResult;

    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2]
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $role = (new GetUserRole($this->data['userToken']))->result();

        if ($role !== $this->data['role']) {
            $this->setResult('error', 'У вас нет доступа к данной операции');
        } else {
            $this->setResultEvent();
        }

    }

    protected function setResultEvent()
    {
        $data = $this->normalizeMatchResultData($this->data['data'] ?? []);

        CIBlockElement::SetPropertyValuesEx($this->data['matchId'], $this->arIbs['matches']['id'], $data);

        $this->setResult('ok', '');
    }

    /**
     * Подставляет производные поля и дефолты, если админ заполнил только счёт.
     *
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    protected function normalizeMatchResultData(array $data): array
    {
        $home = (int)($data[7] ?? $data['7'] ?? 0);
        $guest = (int)($data[8] ?? $data['8'] ?? 0);

        $data[7] = $home;
        $data[8] = $guest;
        $data[26] = $home + $guest;
        $data[25] = $home - $guest;

        if ($data[25] > 0) {
            $data[9] = 'п1';
        } elseif ($data[25] === 0) {
            $data[9] = 'н';
        } else {
            $data[9] = 'п2';
        }

        $defaults = [
            10 => 50,
            12 => 3,
            13 => 0,
            11 => 9,
            14 => 0,
        ];

        foreach ($defaults as $propId => $defaultValue) {
            if (!isset($data[$propId]) || $data[$propId] === '' || $data[$propId] === null) {
                $data[$propId] = $defaultValue;
            }
        }

        $data[10] = max(0, min(100, (int)$data[10]));

        return $data;
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }
}