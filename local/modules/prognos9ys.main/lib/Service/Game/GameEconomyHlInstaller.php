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
    public const TABLE_TREASURE_CHEST = 'prognos9ys_treasure_chest';
    public const TABLE_ACHIEVEMENT_CLAIM = 'prognos9ys_achievement_claim';
    public const TABLE_USER_BANK = 'prognos9ys_user_bank';
    public const TABLE_BANK_DEPOSIT = 'prognos9ys_bank_deposit';
    public const TABLE_BANK_LOAN = 'prognos9ys_bank_loan';
    public const TABLE_TREASURY_SHOP_WAVE = 'prognos9ys_treasury_shop_wave';
    public const TABLE_MATCH_ECONOMY_SETTLE = 'prognos9ys_match_economy_settle';

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
            'UF_RUBLIUS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
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

        $chestHlId = $this->ensureHlBlock('Prognos9ysTreasureChest', self::TABLE_TREASURE_CHEST, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MATCH_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_COUNT' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_TYPE' => ['USER_TYPE_ID' => 'string'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $achievementClaimHlId = $this->ensureHlBlock('Prognos9ysAchievementClaim', self::TABLE_ACHIEVEMENT_CLAIM, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CLAIMED_THRESHOLD' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $userBankHlId = $this->ensureHlBlock('Prognos9ysUserBank', self::TABLE_USER_BANK, [
            'UF_OWNER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_RESERVED' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_LIQUID' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_ACTIVE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $bankDepositHlId = $this->ensureHlBlock('Prognos9ysBankDeposit', self::TABLE_BANK_DEPOSIT, [
            'UF_BANK_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRINCIPAL' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_INTEREST_RATE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_MATCHES_SINCE_START' => ['USER_TYPE_ID' => 'integer'],
            'UF_TERM_MATCHES' => ['USER_TYPE_ID' => 'integer'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_OPENING_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_OPENING_MATCH_NUMBER' => ['USER_TYPE_ID' => 'integer'],
            'UF_LAST_TICK_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CLOSED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CONTRACT_TYPE' => ['USER_TYPE_ID' => 'string'],
        ]);

        $bankLoanHlId = $this->ensureHlBlock('Prognos9ysBankLoan', self::TABLE_BANK_LOAN, [
            'UF_BANK_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRINCIPAL' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_INTEREST_RATE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_MATCHES_SINCE_START' => ['USER_TYPE_ID' => 'integer'],
            'UF_TERM_MATCHES' => ['USER_TYPE_ID' => 'integer'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_OPENING_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_OPENING_MATCH_NUMBER' => ['USER_TYPE_ID' => 'integer'],
            'UF_LAST_TICK_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CLOSED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $treasuryShopHlId = $this->ensureHlBlock('Prognos9ysTreasuryShopWave', self::TABLE_TREASURY_SHOP_WAVE, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MILESTONE' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PROGNOBAKS_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_RUBLIUS_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_3D_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_5D_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        (new LevelService())->seedDefaultTiers();

        $this->ensureStateTreasurySeed();

        return [
            'wallet_hl_id' => $walletHlId,
            'wallet_tx_hl_id' => $txHlId,
            'level_tier_hl_id' => $tierHlId,
            'user_progress_hl_id' => $progressHlId,
            'pending_xp_hl_id' => $pendingHlId,
            'game_bank_hl_id' => $bankHlId,
            'match_bet_hl_id' => $betHlId,
            'treasure_chest_hl_id' => $chestHlId,
            'achievement_claim_hl_id' => $achievementClaimHlId,
            'user_bank_hl_id' => $userBankHlId,
            'bank_deposit_hl_id' => $bankDepositHlId,
            'bank_loan_hl_id' => $bankLoanHlId,
            'treasury_shop_wave_hl_id' => $treasuryShopHlId,
        ];
    }

    /**
     * Добавляет поля казны/лавки к уже установленным HL (идемпотентно).
     */
    public function upgradeTreasuryFeatures(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $bankHlId = $this->ensureHlBlock('Prognos9ysGameBank', self::TABLE_GAME_BANK, [
            'UF_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PROGNOBAKS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_RUBLIUS' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
        ]);

        $depositHlId = $this->ensureHlBlock('Prognos9ysBankDeposit', self::TABLE_BANK_DEPOSIT, [
            'UF_BANK_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRINCIPAL' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_INTEREST_RATE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_MATCHES_SINCE_START' => ['USER_TYPE_ID' => 'integer'],
            'UF_TERM_MATCHES' => ['USER_TYPE_ID' => 'integer'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_OPENING_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_OPENING_MATCH_NUMBER' => ['USER_TYPE_ID' => 'integer'],
            'UF_LAST_TICK_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CLOSED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CONTRACT_TYPE' => ['USER_TYPE_ID' => 'string'],
        ]);

        $treasuryShopHlId = $this->ensureHlBlock('Prognos9ysTreasuryShopWave', self::TABLE_TREASURY_SHOP_WAVE, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MILESTONE' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PROGNOBAKS_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_RUBLIUS_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_3D_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_PREMIUM_5D_BOUGHT' => ['USER_TYPE_ID' => 'boolean'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $this->ensureStateTreasurySeed();

        return [
            'game_bank_hl_id' => $bankHlId,
            'bank_deposit_hl_id' => $depositHlId,
            'treasury_shop_wave_hl_id' => $treasuryShopHlId,
        ];
    }

    /**
     * Реестр матчей с внесённым результатом и прогнанным пересчётом (тур экономики).
     */
    public function upgradeMatchEconomySettlement(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlId = $this->ensureHlBlock('Prognos9ysMatchEconomySettle', self::TABLE_MATCH_ECONOMY_SETTLE, [
            'UF_MATCH_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MATCH_NUMBER' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_SETTLED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        return [
            'match_economy_settle_hl_id' => $hlId,
        ];
    }

    private function ensureStateTreasurySeed(): void
    {
        $repository = new GameEconomyRepository();
        $repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
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
