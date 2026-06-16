<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class GameEconomyHlInstaller
{
    public const TABLE_WALLET = 'prognos9ys_user_wallet';
    public const TABLE_WALLET_TX = 'prognos9ys_wallet_tx';
    public const TABLE_LEVEL_TIER = 'prognos9ys_level_tier';
    public const TABLE_USER_PROGRESS = 'prognos9ys_user_progress';
    public const TABLE_PENDING_XP = 'prognos9ys_pending_xp';
    public const TABLE_GAME_BANK = 'prognos9ys_game_bank';
    public const TABLE_MATCH_BET = 'prognos9ys_match_bet';

    public function install(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $walletHlId = $this->ensureHlBlock('Prognos9ysUserWallet', self::TABLE_WALLET, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PROGNOBAKS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_RUBLIUS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
        ]);

        $txHlId = $this->ensureHlBlock('Prognos9ysWalletTx', self::TABLE_WALLET_TX, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CURRENCY' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_AMOUNT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_BALANCE_AFTER' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_REASON' => ['USER_TYPE_ID' => 'string'],
            'UF_REF_TYPE' => ['USER_TYPE_ID' => 'string'],
            'UF_REF_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $tierHlId = $this->ensureHlBlock('Prognos9ysLevelTier', self::TABLE_LEVEL_TIER, [
            'UF_LEVEL' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MIN_XP' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_TITLE' => ['USER_TYPE_ID' => 'string'],
        ]);

        $progressHlId = $this->ensureHlBlock('Prognos9ysUserProgress', self::TABLE_USER_PROGRESS, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_XP' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
        ]);

        $pendingHlId = $this->ensureHlBlock('Prognos9ysPendingXp', self::TABLE_PENDING_XP, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MATCH_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_POINTS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CLAIMED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $bankHlId = $this->ensureHlBlock('Prognos9ysGameBank', self::TABLE_GAME_BANK, [
            'UF_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PROGNOBAKS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
        ]);

        $betHlId = $this->ensureHlBlock('Prognos9ysMatchBet', self::TABLE_MATCH_BET, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MATCH_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_OUTCOME' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_STAKE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PAYOUT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_SETTLED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        (new LevelService())->seedDefaultTiers();

        return [
            'wallet_hl_id' => $walletHlId,
            'wallet_tx_hl_id' => $txHlId,
            'level_tier_hl_id' => $tierHlId,
            'user_progress_hl_id' => $progressHlId,
            'pending_xp_hl_id' => $pendingHlId,
            'game_bank_hl_id' => $bankHlId,
            'match_bet_hl_id' => $betHlId,
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
