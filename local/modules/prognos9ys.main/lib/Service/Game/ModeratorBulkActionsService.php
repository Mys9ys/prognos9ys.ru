<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Массовые действия модератора (лавка, XP, займы) — по одному игроку за запрос.
 */
class ModeratorBulkActionsService
{
    private GameEconomyRepository $repository;
    private TreasuryShopService $shopService;
    private ExperienceService $experienceService;
    private BankLoanService $loanService;
    private GameEventScopeService $scopeService;
    private AchievementService $achievementService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?TreasuryShopService $shopService = null,
        ?ExperienceService $experienceService = null,
        ?BankLoanService $loanService = null,
        ?GameEventScopeService $scopeService = null,
        ?AchievementService $achievementService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->shopService = $shopService ?? new TreasuryShopService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
        $this->experienceService = $experienceService ?? new ExperienceService($this->repository, null, $this->scopeService);
        $this->loanService = $loanService ?? new BankLoanService($this->repository);
        $this->achievementService = $achievementService ?? new AchievementService($this->repository, $this->scopeService);
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

        $candidates = [];
        foreach ($wallets as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $eligibility = $this->evaluateEligibility($bulkAction, $userId, $wallet, $pendingMap, $claimableMap);
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

            default:
                throw new \InvalidArgumentException('Неизвестное массовое действие');
        }
    }

    /**
     * @param array<int, array{count:int,points:float}> $pendingMap
     * @return array{eligible:bool,hint?:string,skip_reason?:string}
     */
    private function evaluateEligibility(
        string $bulkAction,
        int $userId,
        array $wallet,
        array $pendingMap = [],
        array $claimableMap = []
    ): array {
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

            default:
                return ['eligible' => false, 'skip_reason' => 'Неизвестное действие'];
        }
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
        ], true)) {
            throw new \InvalidArgumentException('Неизвестное массовое действие');
        }
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
