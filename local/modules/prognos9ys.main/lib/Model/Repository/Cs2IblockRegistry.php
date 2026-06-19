<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\Loader;

class Cs2IblockRegistry
{
    public const IBLOCK_TEAMS = 'cs2teams';
    public const IBLOCK_MATCHES = 'cs2matches';
    public const IBLOCK_PROGNOSIS = 'prognoscs2';
    public const IBLOCK_RESULT = 'resultcs2';

    /** @var array<string, int>|null */
    private static ?array $iblockIds = null;

    /** @var array<string, array<string, int>>|null */
    private static ?array $propertyIds = null;

    public function getIblockId(string $code): int
    {
        $this->bootstrap();

        return (int)(self::$iblockIds[$code] ?? 0);
    }

    public function getPropertyId(string $iblockCode, string $propertyCode): int
    {
        $this->bootstrap();

        return (int)(self::$propertyIds[$iblockCode][$propertyCode] ?? 0);
    }

    /** @return array<string, int> */
    public function getPropertyMap(string $iblockCode): array
    {
        $this->bootstrap();

        return self::$propertyIds[$iblockCode] ?? [];
    }

    /** @return array{matches:int,prognosis:int,result:int,teams:int} */
    public function legacyIds(): array
    {
        return [
            'matches' => $this->getIblockId(self::IBLOCK_MATCHES),
            'prognosis' => $this->getIblockId(self::IBLOCK_PROGNOSIS),
            'result' => $this->getIblockId(self::IBLOCK_RESULT),
            'teams' => $this->getIblockId(self::IBLOCK_TEAMS),
        ];
    }

    public static function resetCache(): void
    {
        self::$iblockIds = null;
        self::$propertyIds = null;
    }

    private function bootstrap(): void
    {
        if (self::$iblockIds !== null) {
            return;
        }

        self::$iblockIds = [];
        self::$propertyIds = [];

        if (!Loader::includeModule('iblock')) {
            return;
        }

        foreach ([self::IBLOCK_TEAMS, self::IBLOCK_MATCHES, self::IBLOCK_PROGNOSIS, self::IBLOCK_RESULT] as $code) {
            $row = \CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
            if ($row) {
                self::$iblockIds[$code] = (int)$row['ID'];
            }
        }

        foreach (self::$iblockIds as $iblockCode => $iblockId) {
            self::$propertyIds[$iblockCode] = [];
            $response = \CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId]);
            while ($property = $response->Fetch()) {
                $propCode = (string)($property['CODE'] ?? '');
                if ($propCode !== '') {
                    self::$propertyIds[$iblockCode][$propCode] = (int)$property['ID'];
                }
            }
        }
    }
}
