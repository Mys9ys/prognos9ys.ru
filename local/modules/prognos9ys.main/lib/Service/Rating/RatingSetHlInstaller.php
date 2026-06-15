<?php

namespace Prognos9ys\Main\Service\Rating;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

class RatingSetHlInstaller
{
    public const TABLE_SET = 'prognos9ys_rating_set';
    public const TABLE_MEMBER = 'prognos9ys_rs_member';
    public const TABLE_EVENT = 'prognos9ys_rs_event';

    public function install(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $setHlId = $this->ensureHlBlock('Prognos9ysRatingSet', self::TABLE_SET, [
            'UF_OWNER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_TITLE' => ['USER_TYPE_ID' => 'string'],
            'UF_VISIBILITY' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_SPORT' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ACTIVE' => ['USER_TYPE_ID' => 'boolean', 'SETTINGS' => ['DEFAULT_VALUE' => 1]],
        ]);

        $memberHlId = $this->ensureHlBlock('Prognos9ysRatingSetMember', self::TABLE_MEMBER, [
            'UF_SET_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_SORT' => ['USER_TYPE_ID' => 'integer'],
        ]);

        $eventHlId = $this->ensureHlBlock('Prognos9ysRatingSetEvent', self::TABLE_EVENT, [
            'UF_SET_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
        ]);

        return [
            'set_hl_id' => $setHlId,
            'member_hl_id' => $memberHlId,
            'event_hl_id' => $eventHlId,
        ];
    }

    private function ensureHlBlock(string $name, string $tableName, array $fields): int
    {
        $existing = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
            'select' => ['ID'],
        ])->fetch();

        if ($existing) {
            $hlId = (int)$existing['ID'];
        } else {
            $result = HighloadBlockTable::add([
                'NAME' => $name,
                'TABLE_NAME' => $tableName,
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }

            $hlId = (int)$result->getId();
        }

        foreach ($fields as $fieldName => $config) {
            $this->ensureUserField($hlId, $fieldName, $config);
        }

        return $hlId;
    }

    private function ensureUserField(int $hlId, string $fieldName, array $config): void
    {
        $entityId = 'HLBLOCK_' . $hlId;

        $existing = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
        ])->Fetch();

        if ($existing) {
            return;
        }

        $userField = new \CUserTypeEntity();
        $fieldId = $userField->Add(array_merge([
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [],
        ], $config));

        if (!$fieldId) {
            throw new \RuntimeException('Не удалось создать поле ' . $fieldName . ' для ' . $entityId);
        }
    }
}
