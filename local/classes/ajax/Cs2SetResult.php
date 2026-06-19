<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;
use Prognos9ys\Main\Service\Cs2\Cs2FieldMapper;

class Cs2SetResult extends FootballSetResult
{
    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $registry = new Cs2IblockRegistry();
        $this->arIbs = [
            'matches' => [
                'code' => Cs2IblockRegistry::IBLOCK_MATCHES,
                'id' => $registry->getIblockId(Cs2IblockRegistry::IBLOCK_MATCHES),
            ],
        ];

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
        $registry = new Cs2IblockRegistry();
        $mapper = new Cs2FieldMapper($registry);
        $raw = $this->normalizeMatchResultData($this->data['data'] ?? []);
        $properties = $mapper->matchResultToBitrix($raw, $raw['map_scores_json'] ?? null);

        CIBlockElement::SetPropertyValuesEx(
            $this->data['matchId'],
            $this->arIbs['matches']['id'],
            $properties
        );

        $this->setResult('ok', '');
    }

    protected function normalizeMatchResultData(array $data): array
    {
        $home = (int)($data['maps_home'] ?? $data[7] ?? $data['7'] ?? 0);
        $guest = (int)($data['maps_guest'] ?? $data[8] ?? $data['8'] ?? 0);

        $data['maps_home'] = $home;
        $data['maps_guest'] = $guest;
        $data[7] = $home;
        $data[8] = $guest;
        $data['sum'] = $home + $guest;
        $data[26] = $home + $guest;
        $data['diff'] = $home - $guest;
        $data[25] = $home - $guest;

        if ($data['diff'] > 0) {
            $data['result'] = 'п1';
            $data[9] = 'п1';
        } elseif ($data['diff'] < 0) {
            $data['result'] = 'п2';
            $data[9] = 'п2';
        } else {
            $data['result'] = '';
            $data[9] = '';
        }

        $defaults = [
            'opening_pct' => 50,
            'pistol_pct' => 50,
            'clutches_home' => 0,
            'clutches_guest' => 0,
            10 => 50,
            11 => 50,
            12 => 0,
            13 => 0,
        ];

        foreach ($defaults as $key => $defaultValue) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                $data[$key] = $defaultValue;
            }
        }

        $opening = (int)($data['opening_pct'] ?? $data[10] ?? 50);
        $pistol = (int)($data['pistol_pct'] ?? $data[11] ?? 50);
        $data['opening_pct'] = max(0, min(100, $opening));
        $data['pistol_pct'] = max(0, min(100, $pistol));
        $data[10] = $data['opening_pct'];
        $data[11] = $data['pistol_pct'];

        if (!empty($data['map_scores_json'])) {
            $data['map_scores'] = (string)$data['map_scores_json'];
        }

        return $data;
    }
}
