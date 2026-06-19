<?php

namespace Sprint\Migration;

/**
 * Безопасный поиск ИБ: getIblockIdIfExists в sprint.migration бросает исключение, если ИБ нет.
 */
class Cs2MigrationIblock
{
    public static function findId(string $code, string $type = 'content'): int
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return 0;
        }

        $filter = [
            'CODE' => $code,
            'CHECK_PERMISSIONS' => 'N',
        ];

        if ($type !== '') {
            $filter['TYPE'] = $type;
        }

        $row = \CIBlock::GetList([], $filter)->Fetch();

        return (int)($row['ID'] ?? 0);
    }
}
