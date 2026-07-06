<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

/**
 * Массовые действия модератора (лавка, XP, займы) — по одному игроку за запрос.
 */
class ModeratorBulkActionsService
{
    /** @var array<string, array{code:string,category:string}> */
    private const SELL_LOOT_BULK = [
        'sell_pack_pennant_wc26' => [
            'code' => 'pack_pennant_wc26',
            'category' => ChestLootConfig::CATEGORY_PACK,
        ],
        'sell_pack_scarf_wc26' => [
            'code' => 'pack_scarf_wc26',
            'category' => ChestLootConfig::CATEGORY_PACK,
        ],
        'sell_xp_bank_crafting_25' => [
            'code' => 'xp_bank_crafting_25',
            'category' => ChestLootConfig::CATEGORY_XP_BANK,
        ],
        'sell_xp_bank_mining_25' => [
            'code' => 'xp_bank_mining_25',
            'category' => ChestLootConfig::CATEGORY_XP_BANK,
        ],
    ];

    private GameEconomyRepository $repository;
    private TreasuryShopService $shopService;
    private ExperienceService $experienceService;
    private BankLoanService $loanService;
    private GameEventScopeService $scopeService;
    private AchievementService $achievementService;
    private BotFarmService $botFarmService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?TreasuryShopService $shopService = null,
        ?ExperienceService $experienceService = null,
        ?BankLoanService $loanService = null,
        ?GameEventScopeService $scopeService = null,
        ?AchievementService $achievementService = null,
        ?BotFarmService $botFarmService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->shopService = $shopService ?? new TreasuryShopService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
        $this->experienceService = $experienceService ?? new ExperienceService($this->repository, null, $this->scopeService);
        $this->loanService = $loanService ?? new BankLoanService($this->repository);
        $this->achievementService = $achievementService ?? new AchievementService($this->repository, $this->scopeService);
        $this->botFarmService = $botFarmService ?? new BotFarmService();
    }

    /**
     * @return array{action:string,candidates:array<int,array{user_id:int,name:string,hint:string}>,total:int}
     */
    public function getCandidates(string $bulkAction): array
    {
        $this->assertBulkAction($bulkAction);

        $names = $this->loadUserNames();
        $pendingMap = $bulkAction === 'claim_xp'
            ? $this->repository->getPendingXpAggregatesForScope($this->scopeService)
            : [];

        $wallets = $this->repository->getAllWallets();
        $claimableMap = [];
        if ($bulkAction === 'claim_achievements') {
            $walletUserIds = array_values(array_unique(array_filter(array_map(
                static fn(array $w): int => (int)($w['user_id'] ?? 0),
                $wallets
            ))));
            $claimableMap = $this->achievementService->getClaimableCountMapForUsers($walletUserIds);
        }

        $loanMap = [];
        $treasuryParsed = FarmBulkActionConfig::parseTreasuryAction($bulkAction);
        $craftParsed = FarmBulkActionConfig::parseTreasuryCraftAction($bulkAction);
        if (($treasuryParsed || $craftParsed)
            && (($treasuryParsed['scope'] ?? $craftParsed['scope'] ?? '') === FarmBulkActionConfig::SCOPE_INDEBTED)) {
            $loanMap = $this->repository->getActiveLoanAggregatesByUser();
        }

        $candidates = [];
        foreach ($wallets as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $eligibility = $this->evaluateEligibility(
                $bulkAction,
                $userId,
                $wallet,
                $pendingMap,
                $claimableMap,
                $loanMap
            );
            if (!($eligibility['eligible'] ?? false)) {
                continue;
            }

            $candidates[] = [
                'user_id' => $userId,
                'name' => $names[$userId] ?? ('Игрок #' . $userId),
                'hint' => (string)($eligibility['hint'] ?? ''),
            ];
        }

        return [
            'action' => $bulkAction,
            'candidates' => $candidates,
            'total' => count($candidates),
        ];
    }

    /**
     * @return array{
     *   action:string,
     *   user_id:int,
     *   status:string,
     *   message:string,
     *   detail?:array<string,mixed>
     * }
     */
    public function runOne(string $bulkAction, int $userId): array
    {
        $this->assertBulkAction($bulkAction);

        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $wallet = $this->repository->getWalletByUserId($userId);
        if (!$wallet) {
            return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет кошелька');
        }

        $walletRow = [
            'user_id' => $userId,
            'prognobaks' => round((float)($wallet['UF_PROGNOBAKS'] ?? 0), 1),
            'rublius' => round((float)($wallet['UF_RUBLIUS'] ?? 0), 1),
        ];

        if ($bulkAction === 'claim_xp') {
            try {
                return $this->executeOne($bulkAction, $userId, $walletRow, []);
            } catch (ApiException $e) {
                if ((int)$e->getCode() === 404) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет опыта');
                }

                return $this->oneResult($bulkAction, $userId, 'failed', $e->getMessage());
            } catch (\Throwable $e) {
                return $this->oneResult($bulkAction, $userId, 'failed', $e->getMessage());
            }
        }

        if ($bulkAction === 'claim_achievements') {
            try {
                return $this->executeOne($bulkAction, $userId, $walletRow, []);
            } catch (\Throwable $e) {
                return $this->oneResult($bulkAction, $userId, 'failed', $e->getMessage());
            }
        }

        if ($bulkAction === 'farm_pick_professions'
            || $bulkAction === 'farm_pick_processing_professions'
            || $bulkAction === 'farm_sell_crafted'
            || $bulkAction === 'farm_self_process'
            || $bulkAction === 'open_wc26_chests'
            || $bulkAction === 'open_all_chests'
            || $bulkAction === 'sell_recipes'
            || self::isSellLootBulkAction($bulkAction)
            || FarmBulkActionConfig::isTreasuryAction($bulkAction)
            || FarmBulkActionConfig::isTreasuryCraftAction($bulkAction)) {
            try {
                return $this->executeOne($bulkAction, $userId, $walletRow, []);
            } catch (\Throwable $e) {
                return $this->oneResult($bulkAction, $userId, 'failed', $e->getMessage());
            }
        }

        $pendingMap = [];

        $eligibility = $this->evaluateEligibility($bulkAction, $userId, $walletRow, $pendingMap);
        if (!($eligibility['eligible'] ?? false)) {
            return $this->oneResult(
                $bulkAction,
                $userId,
                'skipped',
                (string)($eligibility['skip_reason'] ?? 'Не подходит')
            );
        }

        try {
            return $this->executeOne($bulkAction, $userId, $walletRow, $eligibility);
        } catch (\Throwable $e) {
            return $this->oneResult($bulkAction, $userId, 'failed', $e->getMessage());
        }
    }

    /** @deprecated Один запрос на всех — риск 504; используйте getCandidates + runOne */
    public function run(string $bulkAction): array
    {
        $preview = $this->getCandidates($bulkAction);
        $result = $this->emptyResult($bulkAction);

        foreach ($preview['candidates'] as $candidate) {
            $one = $this->runOne($bulkAction, (int)$candidate['user_id']);
            if ($one['status'] === 'success') {
                $result['success']++;
            } elseif ($one['status'] === 'skipped') {
                $result['skipped']++;
            } else {
                $result['failed']++;
                $this->pushError($result, (int)$candidate['user_id'], $one['message']);
            }
        }

        return $result;
    }

    private function executeOne(string $bulkAction, int $userId, array $wallet, array $eligibility): array
    {
        switch ($bulkAction) {
            case 'prognobaks_chests':
                $this->shopService->buyChest($userId, GameEconomyConfig::CURRENCY_PROGNOBAKS);

                return $this->oneResult($bulkAction, $userId, 'success', 'Сундук ЧМ за 50 🪙');

            case 'rublius_chests':
                $this->shopService->buyChest($userId, GameEconomyConfig::CURRENCY_RUBLIUS);

                return $this->oneResult($bulkAction, $userId, 'success', 'Сундук ЧМ за 5 💎');

            case 'premium_1d':
                $this->shopService->buyPremium($userId, 'premium_1d');

                return $this->oneResult($bulkAction, $userId, 'success', 'Премиум 1 сутки');

            case 'claim_xp':
                $claim = $this->experienceService->claimAll($userId, true);
                $points = round((float)($claim['claimed_points'] ?? 0), 1);
                $count = (int)($claim['claimed_count'] ?? 0);

                if ($count <= 0 || $points <= 0) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет опыта');
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    '+' . $points . ' XP (' . $count . ' матч.)',
                    ['claimed_points' => $points, 'claimed_count' => $count]
                );

            case 'grant_loans_bet':
            case 'grant_loans_shop':
                return $this->executeGrantLoan($bulkAction, $userId);

            case 'claim_achievements':
                $granted = $this->achievementService->claimAllAvailable($userId);
                $count = count($granted);

                if ($count <= 0) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', 'Нечего забирать');
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    'Ачивки: ' . $count . ' ур.',
                    ['granted_count' => $count, 'granted' => $granted]
                );

            case 'farm_pick_professions':
                $result = $this->botFarmService->pickGatherProfessionIfMissing($userId);

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    (string)($result['status'] ?? 'failed'),
                    (string)($result['message'] ?? ''),
                    $result
                );

            case 'farm_pick_processing_professions':
                $result = $this->botFarmService->pickProcessingProfessionIfSingleGather($userId);

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    (string)($result['status'] ?? 'failed'),
                    (string)($result['message'] ?? ''),
                    $result
                );

            case 'farm_sell_crafted':
                $result = (new ExchangeService($this->repository))->sellAllBasicCraftedMaterials($userId);
                $listedQty = (int)($result['listed_qty'] ?? 0);
                if ($listedQty <= 0) {
                    $message = 'Нечего выставить';
                    foreach ($result['lines'] ?? [] as $line) {
                        if (($line['status'] ?? '') === 'fail') {
                            $message = (string)($line['text'] ?? $message);
                            break;
                        }
                    }

                    return $this->oneResult($bulkAction, $userId, 'skipped', $message, $result);
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    'Выставлено ' . $listedQty . ' шт.',
                    $result
                );

            case 'open_wc26_chests':
                $result = (new ChestOpenService($this->repository))->openWc26Chests($userId, 999);
                $opened = (int)($result['summary']['opened_count'] ?? 0);
                if ($opened <= 0) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет сундуков', $result);
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    'Открыто ' . $opened,
                    $result
                );

            case 'open_all_chests':
                $chestService = new ChestOpenService($this->repository);
                try {
                    $result = $chestService->openAllChests($userId, 999);
                } catch (\RuntimeException $e) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', $e->getMessage());
                }

                $opened = (int)($result['summary']['opened_count'] ?? 0);
                if ($opened <= 0) {
                    return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет сундуков', $result);
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    'Открыто ' . $opened,
                    $result
                );

            case 'sell_recipes':
                $result = (new ExchangeService($this->repository))->sellAllRecipeScrollsAtNominal($userId);
                $listedQty = (int)($result['listed_qty'] ?? 0);
                if ($listedQty <= 0) {
                    $message = 'Нечего выставить';
                    foreach ($result['lines'] ?? [] as $line) {
                        if (($line['status'] ?? '') === 'fail') {
                            $message = (string)($line['text'] ?? $message);
                            break;
                        }
                    }

                    return $this->oneResult($bulkAction, $userId, 'skipped', $message, $result);
                }

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    'success',
                    'Выставлено ' . $listedQty . ' рец.',
                    $result
                );

            case 'farm_self_process':
                $result = $this->botFarmService->runInstantSelfProcess($userId);

                return $this->oneResult(
                    $bulkAction,
                    $userId,
                    (string)($result['status'] ?? 'failed'),
                    (string)($result['message'] ?? ''),
                    $result
                );

            default:
                $sellLoot = self::parseSellLootBulkAction($bulkAction);
                if ($sellLoot) {
                    $result = (new ExchangeService($this->repository))->sellLootItemAtNominal(
                        $userId,
                        (string)$sellLoot['code'],
                        (string)$sellLoot['category']
                    );
                    $listedQty = (int)($result['listed_qty'] ?? 0);
                    if ($listedQty <= 0) {
                        $message = 'Нечего выставить';
                        foreach ($result['lines'] ?? [] as $line) {
                            if (($line['status'] ?? '') === 'fail') {
                                $message = (string)($line['text'] ?? $message);
                                break;
                            }
                        }

                        return $this->oneResult($bulkAction, $userId, 'skipped', $message, $result);
                    }

                    return $this->oneResult(
                        $bulkAction,
                        $userId,
                        'success',
                        'Выставлено ' . $listedQty . ' шт.',
                        $result
                    );
                }

                $craftParsed = FarmBulkActionConfig::parseTreasuryCraftAction($bulkAction);
                if ($craftParsed) {
                    $result = $this->botFarmService->runInstantTreasuryCraft(
                        $userId,
                        (int)$craftParsed['iterations']
                    );

                    return $this->oneResult(
                        $bulkAction,
                        $userId,
                        (string)($result['status'] ?? 'failed'),
                        (string)($result['message'] ?? ''),
                        $result
                    );
                }

                $treasuryParsed = FarmBulkActionConfig::parseTreasuryAction($bulkAction);
                if ($treasuryParsed) {
                    $result = $this->botFarmService->runInstantTreasuryGather(
                        $userId,
                        (int)$treasuryParsed['iterations']
                    );

                    return $this->oneResult(
                        $bulkAction,
                        $userId,
                        (string)($result['status'] ?? 'failed'),
                        (string)($result['message'] ?? ''),
                        $result
                    );
                }

                throw new \InvalidArgumentException('Неизвестное массовое действие');
        }
    }

    /**
     * @param array<int, array{count:int,points:float}> $pendingMap
     * @param array<int, int> $claimableMap
     * @param array<int, array{count:int,total_due:float}> $loanMap
     * @return array{eligible:bool,hint?:string,skip_reason?:string}
     */
    private function evaluateEligibility(
        string $bulkAction,
        int $userId,
        array $wallet,
        array $pendingMap = [],
        array $claimableMap = [],
        array $loanMap = []
    ): array {
        $treasuryParsed = FarmBulkActionConfig::parseTreasuryAction($bulkAction);
        if ($treasuryParsed) {
            return $this->evaluateFarmTreasuryEligibility($userId, $wallet, $treasuryParsed, $loanMap);
        }

        $craftParsed = FarmBulkActionConfig::parseTreasuryCraftAction($bulkAction);
        if ($craftParsed) {
            return $this->evaluateFarmTreasuryCraftEligibility($userId, $wallet, $craftParsed, $loanMap);
        }

        $sellLoot = self::parseSellLootBulkAction($bulkAction);
        if ($sellLoot) {
            $qty = $this->resolveSellLootAvailableQty(
                $userId,
                (string)$sellLoot['code'],
                (string)$sellLoot['category']
            );
            if ($qty <= 0) {
                return ['eligible' => false, 'skip_reason' => 'Нет в наличии'];
            }

            $label = ChestLootConfig::getLabel((string)$sellLoot['code']);

            return ['eligible' => true, 'hint' => $label . ' ×' . $qty];
        }

        switch ($bulkAction) {
            case 'prognobaks_chests':
                $offers = $this->shopService->getCompactRowOffers($userId);
                if (!($offers['prognobaks_available'] ?? false)) {
                    return ['eligible' => false, 'skip_reason' => 'Сундук недоступен'];
                }
                if ($wallet['prognobaks'] < GameEconomyConfig::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE) {
                    return ['eligible' => false, 'skip_reason' => 'Мало прогнобаксов'];
                }

                return ['eligible' => true, 'hint' => 'Сундук 50 🪙'];

            case 'rublius_chests':
                $offers = $this->shopService->getCompactRowOffers($userId);
                if (!($offers['rublius_available'] ?? false)) {
                    return ['eligible' => false, 'skip_reason' => 'Сундук недоступен'];
                }
                if ($wallet['rublius'] < GameEconomyConfig::TREASURY_SHOP_CHEST_RUBLIUS_PRICE) {
                    return ['eligible' => false, 'skip_reason' => 'Мало рублиусов'];
                }

                return ['eligible' => true, 'hint' => 'Сундук 5 💎'];

            case 'premium_1d':
                $offers = $this->shopService->getCompactRowOffers($userId);
                if (!($offers['premium_available'] ?? false)) {
                    return ['eligible' => false, 'skip_reason' => 'Премиум недоступен'];
                }
                if ($wallet['rublius'] < GameEconomyConfig::TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE) {
                    return ['eligible' => false, 'skip_reason' => 'Мало рублиусов'];
                }

                return ['eligible' => true, 'hint' => 'Премиум 1д'];

            case 'claim_xp':
                $pending = $pendingMap[$userId] ?? ['count' => 0, 'points' => 0.0];
                $points = round((float)($pending['points'] ?? 0), 1);
                if ($points <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет опыта'];
                }

                return ['eligible' => true, 'hint' => '+' . $points . ' XP'];

            case 'grant_loans_bet':
            case 'grant_loans_shop':
                $walletMax = $this->getBulkLoanWalletMax($bulkAction);
                if ($wallet['prognobaks'] >= $walletMax) {
                    return [
                        'eligible' => false,
                        'skip_reason' => 'Достаточно средств (≥' . (int)$walletMax . ' 🪙)',
                    ];
                }
                if (!$this->findBestBankForLoan($userId, GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS)) {
                    return ['eligible' => false, 'skip_reason' => 'Нет банка'];
                }

                $purpose = $bulkAction === 'grant_loans_bet' ? 'ставки' : 'лавка';

                return ['eligible' => true, 'hint' => 'Займ 50 🪙 (' . $purpose . ', <' . (int)$walletMax . ')'];

            case 'claim_achievements':
                $count = (int)($claimableMap[$userId] ?? 0);
                if ($count <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет ачивок'];
                }

                return ['eligible' => true, 'hint' => $count . ' наград'];

            case 'farm_pick_professions':
                if ($this->botFarmService->userHasGatherProfession($userId)) {
                    return ['eligible' => false, 'skip_reason' => 'Профессия есть'];
                }

                $code = BotProfessionPickConfig::pickGatheringCodeForUser($userId);
                $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;

                return ['eligible' => true, 'hint' => $label];

            case 'farm_pick_processing_professions':
                $professions = (new ProfessionRepository())->getProfessionsByUserId($userId);
                if (count($professions) !== 1) {
                    return ['eligible' => false, 'skip_reason' => 'Не одна профессия'];
                }

                $onlyCode = (string)($professions[0]['UF_PROFESSION_CODE'] ?? '');
                $onlyDef = ProfessionMaterialConfig::getProfession($onlyCode);
                if (!$onlyDef || ($onlyDef['type'] ?? '') !== 'gather') {
                    return ['eligible' => false, 'skip_reason' => 'Не только добыча'];
                }

                $code = BotProfessionPickConfig::pickProcessingCodeForUser($userId);
                $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;

                return ['eligible' => true, 'hint' => '+ ' . $label];

            case 'farm_sell_crafted':
                $parts = [];
                $professionRepository = new ProfessionRepository();
                foreach (ProfessionMaterialConfig::basicProcessedMaterialCodes() as $code) {
                    $qty = $professionRepository->getUserMaterialQty($userId, $code, false);
                    if ($qty > 0) {
                        $parts[] = ProfessionMaterialConfig::getMaterialLabel($code) . ' ' . $qty;
                    }
                }
                if (!$parts) {
                    return ['eligible' => false, 'skip_reason' => 'Нет материалов'];
                }

                return ['eligible' => true, 'hint' => implode(', ', $parts)];

            case 'farm_self_process':
                $preview = $this->botFarmService->previewSelfProcess($userId);
                if (!($preview['eligible'] ?? false)) {
                    return [
                        'eligible' => false,
                        'skip_reason' => (string)($preview['skip_reason'] ?? 'Не подходит'),
                    ];
                }

                return [
                    'eligible' => true,
                    'hint' => (string)($preview['message'] ?? ''),
                ];

            case 'open_wc26_chests':
                $eventId = $this->scopeService->getAnchorEventId();
                if ($eventId <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет события ЧМ'];
                }
                $count = $this->repository->countOpenableWc26ChestUnits(
                    $userId,
                    $eventId,
                    ChestLootConfig::WC26_OPENABLE_CHEST_TYPES
                );
                if ($count <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет сундуков'];
                }

                return ['eligible' => true, 'hint' => $count . ' сунд.'];

            case 'open_all_chests':
                $count = (new ChestOpenService($this->repository))->countAllOpenableChests($userId);
                if ($count <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет сундуков'];
                }

                return ['eligible' => true, 'hint' => $count . ' сунд.'];

            case 'sell_recipes':
                $qty = (new ExchangeService($this->repository))->countSellableRecipeScrolls($userId);
                if ($qty <= 0) {
                    return ['eligible' => false, 'skip_reason' => 'Нет рецептов'];
                }

                return ['eligible' => true, 'hint' => $qty . ' рец.'];

            default:
                return ['eligible' => false, 'skip_reason' => 'Неизвестное действие'];
        }
    }

    /**
     * @param array{iterations:int,scope:string} $parsed
     * @param array<int, array{count:int,total_due:float}> $loanMap
     * @return array{eligible:bool,hint?:string,skip_reason?:string}
     */
    private function evaluateFarmTreasuryEligibility(
        int $userId,
        array $wallet,
        array $parsed,
        array $loanMap
    ): array {
        $iterations = (int)$parsed['iterations'];
        $scope = (string)$parsed['scope'];

        if ($scope === FarmBulkActionConfig::SCOPE_POOR
            && $wallet['prognobaks'] >= GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX) {
            return [
                'eligible' => false,
                'skip_reason' => 'Не бедный (≥' . (int)GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX . ' 🪙)',
            ];
        }

        if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
            $loans = $loanMap[$userId] ?? ['count' => 0, 'total_due' => 0.0];
            if ((int)($loans['count'] ?? 0) <= 0) {
                return ['eligible' => false, 'skip_reason' => 'Нет займа'];
            }
        }

        if ($this->professionRepositoryHasActiveSession($userId)) {
            return ['eligible' => false, 'skip_reason' => 'Смена активна'];
        }

        $professions = (new ProfessionRepository())->getProfessionsByUserId($userId);
        $code = (string)($professions[0]['UF_PROFESSION_CODE'] ?? '');
        if ($code === '') {
            $code = BotProfessionPickConfig::pickGatheringCodeForUser($userId);
            $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;
            $hint = 'Назначим ' . $label;
        } else {
            $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;
            $hint = $label;
        }

        $hint .= ', ×' . $iterations;

        $laborService = new LaborExchangeService();
        $order = $laborService->findOpenTreasuryOrderForProfession($code);
        if ($order) {
            $remaining = $laborService->getTreasuryOrderRemainingIterations($order);
            $claimIterations = min($iterations, LaborExchangeConfig::MAX_CYCLES_PER_CLAIM, $remaining);
            if ($claimIterations <= 0) {
                return ['eligible' => false, 'skip_reason' => 'В заказе казны не осталось циклов'];
            }

            $hint .= ', биржа';
            if ($scope === FarmBulkActionConfig::SCOPE_POOR) {
                $hint .= ', ' . round($wallet['prognobaks'], 1) . ' 🪙';
            }
            if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
                $due = round((float)(($loanMap[$userId] ?? [])['total_due'] ?? 0), 1);
                $hint .= ', долг ' . $due . ' 🪙';
            }

            return ['eligible' => true, 'hint' => $hint];
        }

        if ($laborService->hasOpenTreasuryGatherOrders()) {
            return ['eligible' => false, 'skip_reason' => 'Нет заказа казны на бирже для профессии'];
        }

        $payTotal = $iterations * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
        $treasury = (new TreasuryService())->getSummary();
        if ((float)($treasury['prognobaks'] ?? 0) < $payTotal) {
            return ['eligible' => false, 'skip_reason' => 'Мало 🪙 в казне'];
        }

        if ($scope === FarmBulkActionConfig::SCOPE_POOR) {
            $hint .= ', ' . round($wallet['prognobaks'], 1) . ' 🪙';
        }
        if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
            $due = round((float)(($loanMap[$userId] ?? [])['total_due'] ?? 0), 1);
            $hint .= ', долг ' . $due . ' 🪙';
        }

        return ['eligible' => true, 'hint' => $hint];
    }

    /**
     * @param array{iterations:int,scope:string} $parsed
     * @param array<int, array{count:int,total_due:float}> $loanMap
     * @return array{eligible:bool,hint?:string,skip_reason?:string}
     */
    private function evaluateFarmTreasuryCraftEligibility(
        int $userId,
        array $wallet,
        array $parsed,
        array $loanMap
    ): array {
        $iterations = (int)$parsed['iterations'];
        $scope = (string)$parsed['scope'];

        if ($scope === FarmBulkActionConfig::SCOPE_POOR
            && $wallet['prognobaks'] >= GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX) {
            return [
                'eligible' => false,
                'skip_reason' => 'Не бедный (≥' . (int)GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX . ' 🪙)',
            ];
        }

        if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
            $loans = $loanMap[$userId] ?? ['count' => 0, 'total_due' => 0.0];
            if ((int)($loans['count'] ?? 0) <= 0) {
                return ['eligible' => false, 'skip_reason' => 'Нет займа'];
            }
        }

        if ($this->professionRepositoryHasActiveSession($userId)) {
            return ['eligible' => false, 'skip_reason' => 'Смена активна'];
        }

        $code = $this->botFarmService->getProcessingProfessionCode($userId);
        if ($code === '') {
            return ['eligible' => false, 'skip_reason' => 'Нет профессии обработки'];
        }

        $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;
        $hint = $label . ', ×' . $iterations;

        $laborService = new LaborExchangeService();
        $order = $laborService->findOpenTreasuryOrderForProfession($code);
        if ($order) {
            $remaining = $laborService->getTreasuryOrderRemainingIterations($order);
            $claimIterations = min($iterations, LaborExchangeConfig::MAX_CYCLES_PER_CLAIM, $remaining);
            if ($claimIterations <= 0) {
                return ['eligible' => false, 'skip_reason' => 'В заказе казны не осталось циклов'];
            }

            $hint .= ', биржа';
            if ($scope === FarmBulkActionConfig::SCOPE_POOR) {
                $hint .= ', ' . round($wallet['prognobaks'], 1) . ' 🪙';
            }
            if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
                $due = round((float)(($loanMap[$userId] ?? [])['total_due'] ?? 0), 1);
                $hint .= ', долг ' . $due . ' 🪙';
            }

            return ['eligible' => true, 'hint' => $hint];
        }

        if ($laborService->hasOpenTreasuryProcessingOrders()) {
            return ['eligible' => false, 'skip_reason' => 'Нет заказа казны на бирже для профессии'];
        }

        $payTotal = $iterations * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
        $treasury = (new TreasuryService())->getSummary();
        if ((float)($treasury['prognobaks'] ?? 0) < $payTotal) {
            return ['eligible' => false, 'skip_reason' => 'Мало 🪙 в казне'];
        }

        if ($scope === FarmBulkActionConfig::SCOPE_POOR) {
            $hint .= ', ' . round($wallet['prognobaks'], 1) . ' 🪙';
        }
        if ($scope === FarmBulkActionConfig::SCOPE_INDEBTED) {
            $due = round((float)(($loanMap[$userId] ?? [])['total_due'] ?? 0), 1);
            $hint .= ', долг ' . $due . ' 🪙';
        }

        return ['eligible' => true, 'hint' => $hint];
    }

    private function findBestBankForLoan(int $borrowerId, float $minAmount): ?array
    {
        $best = null;
        $bestLoanable = 0.0;

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            if ((int)($bank['UF_OWNER_ID'] ?? 0) === $borrowerId) {
                continue;
            }

            $loanable = $this->repository->getUserBankLoanableAmount($bank);
            if ($loanable >= $minAmount && $loanable > $bestLoanable) {
                $best = $bank;
                $bestLoanable = $loanable;
            }
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function loadUserNames(): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn(array $w): int => (int)($w['user_id'] ?? 0),
            $this->repository->getAllWallets()
        ))));

        if (!$userIds) {
            return [];
        }

        $names = [];
        $response = UserTable::getList([
            'filter' => ['@ID' => $userIds],
            'select' => ['ID', 'NAME'],
        ]);

        while ($row = $response->fetch()) {
            $id = (int)$row['ID'];
            $names[$id] = $row['NAME'] ?: ('Игрок #' . $id);
        }

        return $names;
    }

    private function assertBulkAction(string $bulkAction): void
    {
        if (!in_array($bulkAction, [
            'prognobaks_chests',
            'claim_xp',
            'rublius_chests',
            'premium_1d',
            'grant_loans_bet',
            'grant_loans_shop',
            'claim_achievements',
            'farm_pick_professions',
            'farm_pick_processing_professions',
            'farm_sell_crafted',
            'farm_self_process',
            'sell_pack_pennant_wc26',
            'sell_pack_scarf_wc26',
            'sell_xp_bank_crafting_25',
            'sell_xp_bank_mining_25',
            'open_wc26_chests',
            'open_all_chests',
            'sell_recipes',
        ], true) && !FarmBulkActionConfig::isTreasuryAction($bulkAction)
            && !FarmBulkActionConfig::isTreasuryCraftAction($bulkAction)) {
            throw new \InvalidArgumentException('Неизвестное массовое действие');
        }
    }

    private function professionRepositoryHasActiveSession(int $userId): bool
    {
        return (new ProfessionRepository())->getActiveSessionByUserId($userId) !== null;
    }

    private static function isSellLootBulkAction(string $bulkAction): bool
    {
        return isset(self::SELL_LOOT_BULK[$bulkAction]);
    }

    /**
     * @return array{code:string,category:string}|null
     */
    private static function parseSellLootBulkAction(string $bulkAction): ?array
    {
        return self::SELL_LOOT_BULK[$bulkAction] ?? null;
    }

    private function resolveSellLootAvailableQty(int $userId, string $code, string $category): int
    {
        return (new ExchangeInventoryService($this->repository))->getAvailableQty(
            $userId,
            ExchangeConfig::KIND_LOOT,
            $code,
            $category
        );
    }

    /**
     * @return array{action:string,user_id:int,status:string,message:string,detail?:array<string,mixed>}
     */
    private function executeGrantLoan(string $bulkAction, int $userId): array
    {
        $amount = GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS;
        $bank = $this->findBestBankForLoan($userId, $amount);
        if (!$bank) {
            return $this->oneResult($bulkAction, $userId, 'skipped', 'Нет банка с ликвидностью');
        }

        $this->loanService->takeLoan($userId, (int)$bank['ID'], $amount);
        $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
        $purpose = $bulkAction === 'grant_loans_bet' ? 'ставки' : 'лавка';

        return $this->oneResult(
            $bulkAction,
            $userId,
            'success',
            'Займ 50 🪙 (' . $purpose . ', банк #' . (int)$bank['ID'] . ')',
            ['bank_id' => (int)$bank['ID'], 'bank_owner_id' => $ownerId]
        );
    }

    private function getBulkLoanWalletMax(string $bulkAction): float
    {
        if ($bulkAction === 'grant_loans_bet') {
            return GameEconomyConfig::MODERATOR_BULK_LOAN_BET_WALLET_MAX;
        }
        if ($bulkAction === 'grant_loans_shop') {
            return GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX;
        }

        throw new \InvalidArgumentException('Неизвестное массовое действие займа');
    }

    /**
     * @param array<string,mixed> $detail
     * @return array{action:string,user_id:int,status:string,message:string,detail?:array<string,mixed>}
     */
    private function oneResult(
        string $bulkAction,
        int $userId,
        string $status,
        string $message,
        array $detail = []
    ): array {
        $row = [
            'action' => $bulkAction,
            'user_id' => $userId,
            'status' => $status,
            'message' => $message,
        ];

        if ($detail) {
            $row['detail'] = $detail;
        }

        return $row;
    }

    /**
     * @return array{action:string,success:int,skipped:int,failed:int,errors:array<int,array{user_id:int,message:string}>}
     */
    private function emptyResult(string $action): array
    {
        return [
            'action' => $action,
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * @param array{errors:array} $result
     */
    private function pushError(array &$result, int $userId, string $message): void
    {
        if (count($result['errors']) >= 20) {
            return;
        }

        $result['errors'][] = [
            'user_id' => $userId,
            'message' => $message,
        ];
    }
}
