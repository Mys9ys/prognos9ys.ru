<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Iblock\IblockTable;

class MatchIblockMetaRepository
{
    private static ?array $propertyCodes = null;
    private static ?string $stageDetailCode = null;
    private static bool $stageDetailResolved = false;
    private static ?int $matchesIblockId = null;

    public function getMatchesIblockId(): int
    {
        if (self::$matchesIblockId !== null) {
            return self::$matchesIblockId;
        }

        $row = IblockTable::getRow([
            'filter' => ['=CODE' => 'matches'],
            'select' => ['ID'],
        ]);

        self::$matchesIblockId = (int)($row['ID'] ?? 2);

        return self::$matchesIblockId;
    }

    public function hasProperty(string $code): bool
    {
        $this->loadPropertyCodes();

        return !empty(self::$propertyCodes[$code]);
    }

    /**
     * CODE свойства «Этап расширенный» (на бою часто step).
     */
    public function getStageDetailPropertyCode(): ?string
    {
        if (self::$stageDetailResolved) {
            return self::$stageDetailCode;
        }

        self::$stageDetailResolved = true;
        $iblockId = $this->getMatchesIblockId();

        $response = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'NAME' => 'Этап расширенный']);
        if ($response && ($row = $response->Fetch())) {
            $code = trim((string)($row['CODE'] ?? ''));
            if ($code !== '') {
                self::$stageDetailCode = $code;

                return self::$stageDetailCode;
            }
        }

        foreach (['stage_detail', 'stage_d', 'stage_ext', 'extended_stage'] as $code) {
            if ($this->hasProperty($code)) {
                self::$stageDetailCode = $code;

                return self::$stageDetailCode;
            }
        }

        self::$stageDetailCode = null;

        return null;
    }

    /**
     * @return list<string>
     */
    public function buildMatchSelectFields(): array
    {
        $select = [
            'ID',
            'NAME',
            'ACTIVE',
            'DATE_ACTIVE_FROM',
            'XML_ID',
            'PROPERTY_home',
            'PROPERTY_guest',
            'PROPERTY_goal_home',
            'PROPERTY_goal_guest',
            'PROPERTY_result',
            'PROPERTY_group',
            'PROPERTY_stage',
            'PROPERTY_number',
            'PROPERTY_events',
        ];

        foreach (['round', 'bracket_code', 'home_label', 'guest_label', 'step'] as $code) {
            if ($this->hasProperty($code)) {
                $select[] = 'PROPERTY_' . $code;
            }
        }

        $detailCode = $this->getStageDetailPropertyCode();
        if ($detailCode && $detailCode !== 'step' && $this->hasProperty($detailCode)) {
            $select[] = 'PROPERTY_' . $detailCode;
        }

        return $select;
    }

    private function loadPropertyCodes(): void
    {
        if (self::$propertyCodes !== null) {
            return;
        }

        self::$propertyCodes = [];
        $response = \CIBlockProperty::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $this->getMatchesIblockId(), 'ACTIVE' => 'Y']
        );

        while ($row = $response->Fetch()) {
            $code = (string)($row['CODE'] ?? '');
            if ($code !== '') {
                self::$propertyCodes[$code] = true;
            }
        }
    }
}
