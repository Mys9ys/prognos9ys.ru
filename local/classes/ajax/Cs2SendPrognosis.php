<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;
use Prognos9ys\Main\Service\Cs2\Cs2FieldMapper;

class Cs2SendPrognosis extends PrognosisGiveInfo
{
    protected int $prognIb = 0;
    protected $userId;
    protected $checkOld = '';
    protected $arFields = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $registry = new Cs2IblockRegistry();
        $this->prognIb = $registry->getIblockId(Cs2IblockRegistry::IBLOCK_PROGNOSIS);

        if ($data['userToken']) {
            $this->userId = (new GetUserIdForToken($data['userToken']))->getId();
            $mapper = new Cs2FieldMapper($registry);
            $this->arFields = $mapper->prognosisToBitrix(
                $data['fields'] ?? [],
                $data['map_scores_json'] ?? null
            );
        }

        if ($this->userId && $this->prognIb > 0) {
            $this->arFields[$registry->getPropertyId(Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'user_id')] = $this->userId;
            $this->uploadUserPrognosis($registry);
        } else {
            $this->setResult('error', 'Ошибка авторизации или инфоблок CS2 не установлен');
        }
    }

    protected function checkOldPrognosis(Cs2IblockRegistry $registry): void
    {
        $matchPropId = $registry->getPropertyId(Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'match_id');
        $matchId = $this->arFields[$matchPropId] ?? null;

        $res = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->prognIb,
                'PROPERTY_USER_ID' => $this->userId,
                'PROPERTY_MATCH_ID' => $matchId,
            ],
            false,
            [],
            ['ID']
        )->GetNext();

        $this->checkOld = $res['ID'] ?? '';
    }

    protected function uploadUserPrognosis(Cs2IblockRegistry $registry): void
    {
        $this->checkOldPrognosis($registry);

        $numberPropId = $registry->getPropertyId(Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'number');
        $userPropId = $registry->getPropertyId(Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'user_id');
        $number = $this->arFields[$numberPropId] ?? '';
        $userId = $this->arFields[$userPropId] ?? $this->userId;

        $ib = new CIBlockElement;
        $payload = [
            'IBLOCK_ID' => $this->prognIb,
            'DATE_ACTIVE_FROM' => date(\CDatabase::DateFormatToPHP('DD.MM.YYYY HH:MI:SS'), time()),
            'PROPERTY_VALUES' => $this->arFields,
        ];

        if ($this->checkOld) {
            $success = $ib->Update($this->checkOld, $payload);
        } else {
            $payload['NAME'] = 'Участник: ' . $userId . ' Прогноз CS2 на матч: ' . $number;
            $success = (bool)$ib->Add($payload);
        }

        if ($success) {
            $this->setResult('ok', '');
        } else {
            $this->setResult('error', 'Ошибка записи');
        }
    }
}
