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
    public const TABLE_LOOT_ITEM = 'prognos9ys_loot_item';
    public const TABLE_CHEST_OPEN_LOG = 'prognos9ys_chest_open_log';
    public const TABLE_XP_BANK_DRINK_LOG = 'prognos9ys_xp_bank_drink_log';
    public const TABLE_EXCHANGE_LISTING = 'prognos9ys_exchange_listing';
    public const TABLE_EXCHANGE_TRADE = 'prognos9ys_exchange_trade';
    public const TABLE_EXCHANGE_NOMINAL = 'prognos9ys_exchange_nominal';
    public const TABLE_BANK_CONSIGNMENT = 'prognos9ys_bank_consignment';
    public const TABLE_USER_PROFESSION = 'prognos9ys_user_profession';
    public const TABLE_PROFESSION_SESSION = 'prognos9ys_profession_session';
    public const TABLE_USER_MATERIAL = 'prognos9ys_user_material';
    public const TABLE_GOV_WAREHOUSE = 'prognos9ys_gov_warehouse';
    public const TABLE_CONSTRUCTION_PROJECT = 'prognos9ys_construction_project';
    public const TABLE_TREASURY_TX = 'prognos9ys_treasury_tx';
    public const TABLE_LABOR_ORDER = 'prognos9ys_labor_order';

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
            'UF_CONSIGNMENT_ENABLED' => ['USER_TYPE_ID' => 'boolean'],
            'UF_CONSIGNMENT_CATEGORIES' => ['USER_TYPE_ID' => 'string'],
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
     * Журнал операций казны (поступления / выплаты населению).
     */
    public function upgradeTreasuryLedger(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $txHlId = $this->ensureHlBlock('Prognos9ysTreasuryTx', self::TABLE_TREASURY_TX, [
            'UF_BANK_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CURRENCY' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_AMOUNT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_BALANCE_AFTER' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_REASON' => ['USER_TYPE_ID' => 'string'],
            'UF_REF_TYPE' => ['USER_TYPE_ID' => 'string'],
            'UF_REF_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $this->ensureStateTreasurySeed();

        return [
            'treasury_tx_hl_id' => $txHlId,
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

    /**
     * HL предметов из сундуков (банки XP, сертификаты, паки) + журнал открытий.
     */
    public function upgradeChestOpeningHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $lootItemHlId = $this->ensureHlBlock('Prognos9ysLootItem', self::TABLE_LOOT_ITEM, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITEM_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CATEGORY' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_COUNT' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_SEALED' => ['USER_TYPE_ID' => 'string'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $openLogHlId = $this->ensureHlBlock('Prognos9ysChestOpenLog', self::TABLE_CHEST_OPEN_LOG, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CHEST_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CHEST_TYPE' => ['USER_TYPE_ID' => 'string'],
            'UF_MATCH_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_MATCH_NUMBER' => ['USER_TYPE_ID' => 'integer'],
            'UF_ROUND' => ['USER_TYPE_ID' => 'integer'],
            'UF_LOOT_JSON' => ['USER_TYPE_ID' => 'string'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        return [
            'loot_item_hl_id' => $lootItemHlId,
            'chest_open_log_hl_id' => $openLogHlId,
        ];
    }

    /**
     * Журнал выпитых банок XP (для ачивок и аналитики).
     */
    public function upgradeXpBankDrinkLogHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlId = $this->ensureHlBlock('Prognos9ysXpBankDrinkLog', self::TABLE_XP_BANK_DRINK_LOG, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITEM_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_BANK_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PROFESSION_CODE' => ['USER_TYPE_ID' => 'string'],
            'UF_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_XP_GAINED' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        return [
            'xp_bank_drink_log_hl_id' => $hlId,
        ];
    }

    /**
     * HL биржи: лоты, сделки, текущие номиналы SKU.
     */
    public function upgradeExchangeHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $listingHlId = $this->ensureHlBlock('Prognos9ysExchangeListing', self::TABLE_EXCHANGE_LISTING, [
            'UF_SELLER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITEM_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CATEGORY' => ['USER_TYPE_ID' => 'string'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_TEAM_CODE' => ['USER_TYPE_ID' => 'string'],
            'UF_QTY_TOTAL' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_QTY_REMAINING' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRICE_PER_UNIT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_NOMINAL_SNAPSHOT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ESCROW_REF_TYPE' => ['USER_TYPE_ID' => 'string'],
            'UF_ESCROW_REF_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_EXPIRES_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_SELLER_BANK_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CONSIGNMENT_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_ORIGINAL_USER_ID' => ['USER_TYPE_ID' => 'integer'],
        ]);

        $tradeHlId = $this->ensureHlBlock('Prognos9ysExchangeTrade', self::TABLE_EXCHANGE_TRADE, [
            'UF_LISTING_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_SELLER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_BUYER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITEM_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CATEGORY' => ['USER_TYPE_ID' => 'string'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_TEAM_CODE' => ['USER_TYPE_ID' => 'string'],
            'UF_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRICE_PER_UNIT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_TOTAL_PRICE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_COMMISSION' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_SELLER_NET' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $nominalHlId = $this->ensureHlBlock('Prognos9ysExchangeNominal', self::TABLE_EXCHANGE_NOMINAL, [
            'UF_ITEM_KEY' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_NOMINAL' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_BASE_NOMINAL' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        return [
            'exchange_listing_hl_id' => $listingHlId,
            'exchange_trade_hl_id' => $tradeHlId,
            'exchange_nominal_hl_id' => $nominalHlId,
        ];
    }

    /**
     * HL комиссионки банка + поля на user_bank и exchange_listing.
     */
    public function upgradeBankConsignmentHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $this->ensureHlBlock('Prognos9ysUserBank', self::TABLE_USER_BANK, [
            'UF_CONSIGNMENT_ENABLED' => ['USER_TYPE_ID' => 'boolean'],
            'UF_CONSIGNMENT_CATEGORIES' => ['USER_TYPE_ID' => 'string'],
        ]);

        $this->ensureHlBlock('Prognos9ysExchangeListing', self::TABLE_EXCHANGE_LISTING, [
            'UF_SELLER_BANK_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_CONSIGNMENT_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_ORIGINAL_USER_ID' => ['USER_TYPE_ID' => 'integer'],
        ]);

        $consignmentHlId = $this->ensureHlBlock('Prognos9ysBankConsignment', self::TABLE_BANK_CONSIGNMENT, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_BANK_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_LISTING_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITEM_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITEM_CATEGORY' => ['USER_TYPE_ID' => 'string'],
            'UF_EVENT_ID' => ['USER_TYPE_ID' => 'integer'],
            'UF_TEAM_CODE' => ['USER_TYPE_ID' => 'string'],
            'UF_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PRICE_PER_UNIT' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_INSTANT_PAID' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_RELIST_COUNT' => ['USER_TYPE_ID' => 'integer'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $this->seedConsignmentEnabledForActiveBanks();

        return [
            'bank_consignment_hl_id' => $consignmentHlId,
        ];
    }

    private function seedConsignmentEnabledForActiveBanks(): void
    {
        $repository = new GameEconomyRepository();
        $defaultCategories = BankConsignmentConfig::encodeCategoryFlags(
            BankConsignmentConfig::defaultCategoryFlags()
        );

        foreach ($repository->getActiveUserBanks(500) as $bank) {
            $bankId = (int)($bank['ID'] ?? 0);
            if ($bankId <= 0) {
                continue;
            }

            $updates = [];
            $flag = $bank['UF_CONSIGNMENT_ENABLED'] ?? null;
            if ($flag === null || $flag === '' || $flag === 0 || $flag === '0') {
                $updates['UF_CONSIGNMENT_ENABLED'] = 'Y';
            }

            $categories = trim((string)($bank['UF_CONSIGNMENT_CATEGORIES'] ?? ''));
            if ($categories === '') {
                $updates['UF_CONSIGNMENT_CATEGORIES'] = $defaultCategories;
            }

            if ($updates !== []) {
                $repository->updateUserBank($bankId, $updates);
            }
        }
    }

    /**
     * HL фарма: профессии, сессии работы, материалы, госсклад, стройки.
     */
    public function upgradeProfessionHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $professionHlId = $this->ensureHlBlock('Prognos9ysUserProfession', self::TABLE_USER_PROFESSION, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PROFESSION_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_LEVEL' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_XP' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_NORMAL_YIELD' => ['USER_TYPE_ID' => 'integer'],
            'UF_PREMIUM_YIELD' => ['USER_TYPE_ID' => 'integer'],
            'UF_SLOT_INDEX' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $sessionHlId = $this->ensureHlBlock('Prognos9ysProfessionSession', self::TABLE_PROFESSION_SESSION, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PROFESSION_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_WORK_MODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_ITERATIONS_DONE' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITERATIONS_TOTAL' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_NEXT_TICK_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_STARTED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_LAST_RESULT_JSON' => ['USER_TYPE_ID' => 'string'],
        ]);

        $materialHlId = $this->ensureHlBlock('Prognos9ysUserMaterial', self::TABLE_USER_MATERIAL, [
            'UF_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_MATERIAL_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_IS_PREMIUM' => ['USER_TYPE_ID' => 'string'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $warehouseHlId = $this->ensureHlBlock('Prognos9ysGovWarehouse', self::TABLE_GOV_WAREHOUSE, [
            'UF_MATERIAL_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $projectHlId = $this->ensureHlBlock('Prognos9ysConstructionProject', self::TABLE_CONSTRUCTION_PROJECT, [
            'UF_OWNER_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_RECIPE_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PROGRESS' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_STASH_JSON' => ['USER_TYPE_ID' => 'string'],
            'UF_BRIGADE_JSON' => ['USER_TYPE_ID' => 'string'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        return [
            'user_profession_hl_id' => $professionHlId,
            'profession_session_hl_id' => $sessionHlId,
            'user_material_hl_id' => $materialHlId,
            'gov_warehouse_hl_id' => $warehouseHlId,
            'construction_project_hl_id' => $projectHlId,
        ];
    }

    /**
     * HL биржи труда + поле заказа на сессии фарма.
     */
    public function upgradeLaborExchangeHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $laborOrderHlId = $this->ensureHlBlock('Prognos9ysLaborOrder', self::TABLE_LABOR_ORDER, [
            'UF_POSTER_USER_ID' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_POSTER_KIND' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_PROFESSION_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_OUTPUT_CODE' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_INPUT_CODE' => ['USER_TYPE_ID' => 'string'],
            'UF_ITERATIONS_TOTAL' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_ITERATIONS_DONE' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_INPUT_ESCROW_QTY' => ['USER_TYPE_ID' => 'integer', 'MANDATORY' => 'Y'],
            'UF_PAY_PER_CYCLE' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_COIN_ESCROW' => ['USER_TYPE_ID' => 'double', 'SETTINGS' => ['PRECISION' => 1]],
            'UF_STATUS' => ['USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y'],
            'UF_CREATED_AT' => ['USER_TYPE_ID' => 'datetime'],
            'UF_UPDATED_AT' => ['USER_TYPE_ID' => 'datetime'],
        ]);

        $this->ensureHlBlock('Prognos9ysProfessionSession', self::TABLE_PROFESSION_SESSION, [
            'UF_LABOR_ORDER_ID' => ['USER_TYPE_ID' => 'integer'],
        ]);

        return [
            'labor_order_hl_id' => $laborOrderHlId,
        ];
    }

    /**
     * Счётчик активированных сертификатов на профессию (доп. слоты).
     */
    public function upgradeProfessionCertificateHl(): array
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $this->ensureHlBlock('Prognos9ysUserProgress', self::TABLE_USER_PROGRESS, [
            'UF_PROFESSION_CERT_SLOTS' => ['USER_TYPE_ID' => 'integer'],
        ]);

        return [];
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
