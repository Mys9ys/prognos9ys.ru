<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class TreasuryShopService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private TreasuryService $treasuryService;
    private TreasureService $treasureService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?WalletService $walletService = null,
        ?TreasuryService $treasuryService = null,
        ?TreasureService $treasureService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->treasuryService = $treasuryService ?? new TreasuryService($this->repository);
        $this->treasureService = $treasureService ?? new TreasureService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function getShopState(int $userId): array
    {
        $eventId = $this->scopeService->getAnchorEventId();
        $currentTour = $this->scopeService->getLastSettledMatchForEvent($eventId)['number'];
        $activeMilestone = $this->resolveActiveMilestone($userId, $currentTour);
        $shopOpen = $currentTour >= GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE;

        return [
            'event_id' => $eventId,
            'current_tour' => $currentTour,
            'shop_open' => $shopOpen,
            'active_milestone' => $activeMilestone,
            'offers' => $shopOpen
                ? $this->buildMergedOffers($userId, $currentTour)
                : [],
            'next_milestone' => $this->nextMilestoneAfter($activeMilestone, $currentTour),
        ];
    }

    /**
     * После пересчёта матча с этапом лавки (40, 50, 60…) создаёт записи волн для игроков.
     *
     * @return array{
     *   is_milestone:bool,
     *   milestone:int,
     *   eligible:int,
     *   waves_created:int,
     *   waves_existing:int,
     *   log_text:string
     * }
     */
    public function provisionWavesForSettledMatch(int $matchId, bool $dryRun = false): array
    {
        $empty = [
            'is_milestone' => false,
            'milestone' => 0,
            'eligible' => 0,
            'waves_created' => 0,
            'waves_existing' => 0,
            'log_text' => '',
        ];

        if ($matchId <= 0) {
            return $empty;
        }

        $eventId = $this->scopeService->getEventIdForMatch($matchId);
        $matchNumber = $this->scopeService->getMatchNumber($matchId);
        $anchorEventId = $this->scopeService->getAnchorEventId();

        if ($eventId !== $anchorEventId || $matchNumber <= 0) {
            return $empty;
        }

        if (!$this->isTreasuryShopMilestone($matchNumber)) {
            return $empty;
        }

        $userIds = $this->resolveUserIdsForMilestoneProvision($matchNumber);
        $created = 0;
        $existing = 0;

        foreach ($userIds as $userId) {
            if ($this->repository->getTreasuryShopWave($userId, $matchNumber)) {
                $existing++;
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            $this->ensureWaveRow($userId, $matchNumber);
            $created++;
        }

        $eligible = count($userIds);
        $logText = $this->buildProvisionLogText($matchNumber, $eligible, $created, $existing);

        return [
            'is_milestone' => true,
            'milestone' => $matchNumber,
            'eligible' => $eligible,
            'waves_created' => $created,
            'waves_existing' => $existing,
            'log_text' => $logText,
        ];
    }

    public function buyChest(int $userId, string $currency, int $milestone = 0): array
    {
        if (!in_array($currency, [
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            GameEconomyConfig::CURRENCY_RUBLIUS,
        ], true)) {
            throw new \InvalidArgumentException('Некорректная валюта покупки');
        }

        $state = $this->getShopState($userId);
        if (!($state['shop_open'] ?? false)) {
            throw new \RuntimeException('Лавка пока недоступна');
        }

        $baseKey = $currency === GameEconomyConfig::CURRENCY_PROGNOBAKS
            ? 'prognobaks_chest'
            : 'rublius_chest';

        $offer = $this->findOffer($state['offers'] ?? [], $baseKey, $milestone);
        if (!$offer || !($offer['available'] ?? false)) {
            throw new \RuntimeException('Предложение недоступно');
        }

        if ($offer['bought'] ?? false) {
            throw new \RuntimeException('Сундук уже куплен');
        }

        $milestone = (int)($offer['milestone'] ?? 0);
        $price = (float)($offer['price'] ?? 0);
        $wave = $this->ensureWaveRow($userId, $milestone);
        $waveId = (int)$wave['ID'];

        $charged = $this->chargeWalletForShop(
            $userId,
            $currency,
            $price,
            'treasury_shop_chest',
            $waveId
        );

        if ($charged) {
            $this->treasuryService->credit($currency, $price, 'treasury_shop_wave', $waveId);
        }

        $field = $currency === GameEconomyConfig::CURRENCY_PROGNOBAKS
            ? 'UF_PROGNOBAKS_BOUGHT'
            : 'UF_RUBLIUS_BOUGHT';

        $this->repository->updateTreasuryShopWave($waveId, [
            $field => true,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        $this->treasureService->grantShopChest($userId, $milestone, $currency);

        return [
            'purchase' => [
                'milestone' => $milestone,
                'currency' => $currency,
                'price' => $price,
            ],
            'shop' => $this->getShopState($userId),
        ];
    }

    public function buyPremium(int $userId, string $offerKey = 'premium_1d', int $milestone = 0): array
    {
        $state = $this->getShopState($userId);
        if (!($state['shop_open'] ?? false)) {
            throw new \RuntimeException('Лавка пока недоступна');
        }

        $baseKey = $this->normalizePremiumOfferKey($offerKey);
        $offer = $this->findOffer($state['offers'] ?? [], $baseKey, $milestone, $offerKey);
        if (!$offer || !($offer['available'] ?? false)) {
            throw new \RuntimeException('Предложение недоступно');
        }

        if ($offer['bought'] ?? false) {
            throw new \RuntimeException('Свиток уже куплен');
        }

        $currency = GameEconomyConfig::CURRENCY_RUBLIUS;
        $price = (float)($offer['price'] ?? 0);
        $days = (int)($offer['days'] ?? 1);
        $milestone = (int)($offer['milestone'] ?? 0);
        $wave = $this->ensureWaveRow($userId, $milestone);
        $waveId = (int)$wave['ID'];

        $charged = $this->chargeWalletForShop(
            $userId,
            $currency,
            $price,
            'treasury_shop_premium',
            $waveId
        );

        if ($charged) {
            $this->treasuryService->credit($currency, $price, 'treasury_shop_wave', $waveId);
        }

        $boughtField = $this->getPremiumBoughtField($baseKey);
        $this->repository->updateTreasuryShopWave($waveId, [
            $boughtField => true,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        $this->treasureService->grantPremiumScroll($userId, $milestone, $days);

        return [
            'purchase' => [
                'milestone' => $milestone,
                'offer' => $offerKey,
                'days' => $days,
                'currency' => $currency,
                'price' => $price,
            ],
            'shop' => $this->getShopState($userId),
        ];
    }

    /**
     * Компактный статус лавки для строки рейтинга.
     *
     * @return array{
     *   active_milestone:int,
     *   prognobaks_bought:bool,
     *   rublius_bought:bool,
     *   prognobaks_available:bool,
     *   rublius_available:bool,
     *   premium_bought:bool,
     *   premium_available:bool
     * }
     */
    public function getCompactRowOffers(int $userId): array
    {
        $state = $this->getShopState($userId);
        $offers = $state['offers'] ?? [];
        $milestone = (int)($state['active_milestone'] ?? 0);
        $shopOpen = (bool)($state['shop_open'] ?? false);

        return [
            'active_milestone' => $milestone,
            'prognobaks_bought' => !$shopOpen || !$this->hasAvailableOffer($offers, 'prognobaks_chest'),
            'rublius_bought' => !$shopOpen || !$this->hasAvailableOffer($offers, 'rublius_chest'),
            'premium_bought' => !$shopOpen || !$this->hasAvailableOffer($offers, 'premium_1d'),
            'prognobaks_available' => $shopOpen && $this->hasAvailableOffer($offers, 'prognobaks_chest'),
            'rublius_available' => $shopOpen && $this->hasAvailableOffer($offers, 'rublius_chest'),
            'premium_available' => $shopOpen && $this->hasAvailableOffer($offers, 'premium_1d'),
        ];
    }

    private function resolveActiveMilestone(int $userId, int $currentTour): int
    {
        if ($currentTour < GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
            return 0;
        }

        $milestones = GameEconomyConfig::getTreasuryShopMilestonesUpTo($currentTour);

        return $milestones ? (int)end($milestones) : 0;
    }

    /**
     * Все незакупленные позиции по всем открытым волнам (40, 50…).
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildMergedOffers(int $userId, int $currentTour): array
    {
        $offers = [];
        $milestones = GameEconomyConfig::getTreasuryShopMilestonesUpTo($currentTour);
        $showWaveInLabel = count($milestones) > 1;

        foreach ($milestones as $milestone) {
            $wave = $this->ensureWaveRow($userId, (int)$milestone);

            foreach ($this->buildWaveOffers($wave, (int)$milestone) as $baseKey => $offer) {
                $offer['base_key'] = $baseKey;
                $offer['milestone'] = (int)$milestone;
                $offer['key'] = 'm' . $milestone . '_' . $baseKey;

                if ($showWaveInLabel) {
                    $offer['label'] = trim((string)($offer['label'] ?? ''))
                        . ' · тур ' . $milestone;
                }

                $offers[$offer['key']] = $offer;
            }
        }

        return $offers;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildWaveOffers(array $wave, int $milestone): array
        $pBought = $this->isTruthy($wave['UF_PROGNOBAKS_BOUGHT'] ?? false);
        $rBought = $this->isTruthy($wave['UF_RUBLIUS_BOUGHT'] ?? false);
        $premium1dBought = $this->isTruthy($wave['UF_PREMIUM_BOUGHT'] ?? false);

        return [
            'prognobaks_chest' => [
                'label' => 'Сундук ЧМ-26',
                'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'bought' => $pBought,
                'available' => !$pBought,
            ],
            'rublius_chest' => [
                'label' => 'Сундук ЧМ-26',
                'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $rBought,
                'available' => !$rBought,
            ],
            'premium_1d' => [
                'label' => 'Премиум 1 сутки',
                'days' => 1,
                'emoji' => '📜',
                'price' => GameEconomyConfig::TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $premium1dBought,
                'available' => !$premium1dBought,
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $offers
     */
    private function findOffer(array $offers, string $baseKey, int $milestone = 0, string $offerKey = ''): ?array
    {
        if ($offerKey !== '' && isset($offers[$offerKey])) {
            return $offers[$offerKey];
        }

        foreach ($offers as $offer) {
            if (($offer['base_key'] ?? '') !== $baseKey) {
                continue;
            }

            if ($milestone > 0 && (int)($offer['milestone'] ?? 0) !== $milestone) {
                continue;
            }

            if ($offer['available'] ?? false) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $offers
     */
    private function hasAvailableOffer(array $offers, string $baseKey): bool
    {
        foreach ($offers as $offer) {
            if (($offer['base_key'] ?? '') === $baseKey && ($offer['available'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePremiumOfferKey(string $offerKey): string
    {
        if (preg_match('/^m\d+_(premium(?:_\dd)?)$/', $offerKey, $matches)) {
            return $matches[1];
        }

        return $offerKey;
    }

    private function getPremiumBoughtField(string $offerKey): string
    {
        if (!in_array($offerKey, ['premium_1d', 'premium'], true)) {
            throw new \InvalidArgumentException('Некорректное предложение премиума');
        }

        return 'UF_PREMIUM_BOUGHT';
    }

    private function nextMilestoneAfter(int $activeMilestone, int $currentTour): int
    {
        if ($activeMilestone <= 0) {
            return GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE;
        }

        $next = $activeMilestone + GameEconomyConfig::TREASURY_SHOP_MILESTONE_STEP;

        return $next > $currentTour ? $next : 0;
    }

    private function ensureWaveRow(int $userId, int $milestone): array
    {
        $existing = $this->repository->getTreasuryShopWave($userId, $milestone);
        if ($existing) {
            return $existing;
        }

        $now = new DateTime();
        $id = $this->repository->addTreasuryShopWave([
            'UF_USER_ID' => $userId,
            'UF_MILESTONE' => $milestone,
            'UF_PROGNOBAKS_BOUGHT' => false,
            'UF_RUBLIUS_BOUGHT' => false,
            'UF_PREMIUM_BOUGHT' => false,
            'UF_PREMIUM_3D_BOUGHT' => false,
            'UF_PREMIUM_5D_BOUGHT' => false,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $row = $this->repository->getTreasuryShopWaveById($id);

        if (!$row) {
            throw new \RuntimeException('Не удалось создать волну лавки');
        }

        return $row;
    }

    private function isTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'Y';
    }

    /**
     * @return bool true если списание выполнено сейчас
     */
    private function chargeWalletForShop(
        int $userId,
        string $currency,
        float $price,
        string $reason,
        int $waveId
    ): bool {
        if ($this->repository->hasWalletTx($userId, $reason, 'treasury_shop_wave', $waveId, $currency)) {
            return false;
        }

        $wallet = $this->walletService->getWalletSummary($userId);
        $balanceKey = $currency === GameEconomyConfig::CURRENCY_RUBLIUS ? 'rublius' : 'prognobaks';

        if (round((float)($wallet[$balanceKey] ?? 0), 1) < round($price, 1)) {
            throw new \RuntimeException('Недостаточно средств');
        }

        $this->walletService->debit(
            $userId,
            $currency,
            $price,
            $reason,
            'treasury_shop_wave',
            $waveId
        );

        if (!$this->repository->hasWalletTx($userId, $reason, 'treasury_shop_wave', $waveId, $currency)) {
            throw new \RuntimeException('Списание не зафиксировано в журнале кошелька');
        }

        return true;
    }

    /**
     * Доначислить пропущенные списания за уже отмеченные покупки (ручной repair).
     *
     * @return array{fixed:int,skipped:int,errors:array<int,string>}
     */
    public function repairMissingShopCharges(bool $dryRun = true): array
    {
        $result = ['fixed' => 0, 'skipped' => 0, 'errors' => []];
        $dataClass = $this->repository->getTreasuryShopWaveDataClass();
        $response = $dataClass::getList([
            'select' => ['*'],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($wave = $response->fetch()) {
            $userId = (int)($wave['UF_USER_ID'] ?? 0);
            $waveId = (int)($wave['ID'] ?? 0);
            $milestone = (int)($wave['UF_MILESTONE'] ?? 0);

            if ($userId <= 0 || $waveId <= 0) {
                continue;
            }

            $jobs = [];

            if ($this->isTruthy($wave['UF_RUBLIUS_BOUGHT'] ?? false)) {
                $jobs[] = [
                    'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                    'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_RUBLIUS_PRICE,
                    'reason' => 'treasury_shop_chest',
                    'grant_type' => 'rublius_chest',
                ];
            }

            if ($this->isTruthy($wave['UF_PROGNOBAKS_BOUGHT'] ?? false)) {
                $jobs[] = [
                    'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE,
                    'reason' => 'treasury_shop_chest',
                    'grant_type' => 'prognobaks_chest',
                ];
            }

            if ($this->isTruthy($wave['UF_PREMIUM_BOUGHT'] ?? false)) {
                $jobs[] = [
                    'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                    'price' => GameEconomyConfig::TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE,
                    'reason' => 'treasury_shop_premium',
                    'grant_type' => 'premium',
                ];
            }

            foreach ($jobs as $job) {
                if ($this->repository->hasWalletTx(
                    $userId,
                    $job['reason'],
                    'treasury_shop_wave',
                    $waveId,
                    $job['currency']
                )) {
                    $result['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $result['fixed']++;
                    continue;
                }

                try {
                    $this->walletService->getWalletSummary($userId);
                    $charged = $this->chargeWalletForShop(
                        $userId,
                        $job['currency'],
                        (float)$job['price'],
                        $job['reason'],
                        $waveId
                    );
                    if ($charged) {
                        $this->treasuryService->credit(
                            $job['currency'],
                            (float)$job['price'],
                            'treasury_shop_wave',
                            $waveId
                        );
                    }
                    $this->grantRepairReward($userId, $milestone, (string)$job['grant_type']);
                    $result['fixed']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = 'user #' . $userId . ' wave #' . $waveId . ': ' . $e->getMessage();
                }
            }
        }

        return $result;
    }

    private function grantRepairReward(int $userId, int $milestone, string $grantType): void
    {
        if ($grantType === 'rublius_chest') {
            $this->treasureService->grantShopChest($userId, $milestone, GameEconomyConfig::CURRENCY_RUBLIUS);

            return;
        }

        if ($grantType === 'prognobaks_chest') {
            $this->treasureService->grantShopChest($userId, $milestone, GameEconomyConfig::CURRENCY_PROGNOBAKS);

            return;
        }

        if ($grantType === 'premium') {
            $this->treasureService->grantPremiumScroll($userId, $milestone, 1);
        }
    }

    private function isTreasuryShopMilestone(int $matchNumber): bool
    {
        if ($matchNumber < GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
            return false;
        }

        if ($matchNumber > 200) {
            return false;
        }

        return ($matchNumber - GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE)
            % GameEconomyConfig::TREASURY_SHOP_MILESTONE_STEP === 0;
    }

    /**
     * @return int[]
     */
    private function resolveUserIdsForMilestoneProvision(int $milestone): array
    {
        return $this->repository->getDistinctWalletUserIds();
    }

    private function buildProvisionLogText(int $milestone, int $eligible, int $created, int $existing): string
    {
        if ($milestone === GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
            if ($eligible <= 0) {
                return 'Лавка ЧМ-26: открыта волна ' . $milestone . ' (нет кошельков игроков)';
            }

            return 'Лавка ЧМ-26: открыта волна ' . $milestone
                . ' — записей ' . ($created + $existing)
                . ' (новых ' . $created . ')';
        }

        if ($eligible <= 0) {
            return 'Лавка ЧМ-26: волна ' . $milestone . ' — без пополнения (нет кошельков игроков)';
        }

        if ($created > 0) {
            return 'Лавка ЧМ-26 пополнена: волна ' . $milestone
                . ' — ' . $eligible . ' игроков, новых записей ' . $created
                . ($existing > 0 ? ', уже было ' . $existing : '');
        }

        return 'Лавка ЧМ-26 пополнена: волна ' . $milestone
            . ' — ' . $eligible . ' игроков (записи уже были)';
    }
}
