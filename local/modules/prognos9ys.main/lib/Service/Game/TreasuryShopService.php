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

        return [
            'event_id' => $eventId,
            'current_tour' => $currentTour,
            'shop_open' => $currentTour >= GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE,
            'active_milestone' => $activeMilestone,
            'offers' => $activeMilestone > 0
                ? $this->buildOffers($userId, $activeMilestone, $currentTour)
                : [],
            'next_milestone' => $this->nextMilestoneAfter($activeMilestone, $currentTour),
        ];
    }

    public function buyChest(int $userId, string $currency): array
    {
        if (!in_array($currency, [
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            GameEconomyConfig::CURRENCY_RUBLIUS,
        ], true)) {
            throw new \InvalidArgumentException('Некорректная валюта покупки');
        }

        $state = $this->getShopState($userId);
        $milestone = (int)($state['active_milestone'] ?? 0);

        if ($milestone <= 0) {
            throw new \RuntimeException('Лавка пока недоступна');
        }

        $offers = $state['offers'] ?? [];
        $offerKey = $currency === GameEconomyConfig::CURRENCY_PROGNOBAKS
            ? 'prognobaks_chest'
            : 'rublius_chest';

        $offer = $offers[$offerKey] ?? null;
        if (!$offer || !($offer['available'] ?? false)) {
            throw new \RuntimeException('Предложение недоступно');
        }

        if ($offer['bought'] ?? false) {
            throw new \RuntimeException('Сундук уже куплен');
        }

        $price = (float)($offer['price'] ?? 0);
        $wave = $this->ensureWaveRow($userId, $milestone);

        $this->walletService->debit(
            $userId,
            $currency,
            $price,
            'treasury_shop_chest',
            'treasury_shop_wave',
            (int)$wave['ID']
        );

        $this->treasuryService->credit($currency, $price, 'treasury_shop_wave', (int)$wave['ID']);

        $field = $currency === GameEconomyConfig::CURRENCY_PROGNOBAKS
            ? 'UF_PROGNOBAKS_BOUGHT'
            : 'UF_RUBLIUS_BOUGHT';

        $this->repository->updateTreasuryShopWave((int)$wave['ID'], [
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

    public function buyPremium(int $userId, string $offerKey = 'premium_1d'): array
    {
        $state = $this->getShopState($userId);
        $milestone = (int)($state['active_milestone'] ?? 0);

        if ($milestone <= 0) {
            throw new \RuntimeException('Лавка пока недоступна');
        }

        $offer = ($state['offers'] ?? [])[$offerKey] ?? null;
        if (!$offer || !($offer['available'] ?? false)) {
            throw new \RuntimeException('Предложение недоступно');
        }

        if ($offer['bought'] ?? false) {
            throw new \RuntimeException('Свиток уже куплен');
        }

        $currency = GameEconomyConfig::CURRENCY_RUBLIUS;
        $price = (float)($offer['price'] ?? 0);
        $days = (int)($offer['days'] ?? 1);
        $wave = $this->ensureWaveRow($userId, $milestone);

        $this->walletService->debit(
            $userId,
            $currency,
            $price,
            'treasury_shop_premium',
            'treasury_shop_wave',
            (int)$wave['ID']
        );

        $this->treasuryService->credit($currency, $price, 'treasury_shop_wave', (int)$wave['ID']);

        $boughtField = $this->getPremiumBoughtField($offerKey);
        $this->repository->updateTreasuryShopWave((int)$wave['ID'], [
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
     *   rublius_available:bool
     * }
     */
    public function getCompactRowOffers(int $userId): array
    {
        $state = $this->getShopState($userId);
        $offers = $state['offers'] ?? [];
        $prognobaks = $offers['prognobaks_chest'] ?? [];
        $rublius = $offers['rublius_chest'] ?? [];
        $milestone = (int)($state['active_milestone'] ?? 0);
        $shopOpen = (bool)($state['shop_open'] ?? false) && $milestone > 0;

        return [
            'active_milestone' => $milestone,
            'prognobaks_bought' => (bool)($prognobaks['bought'] ?? false),
            'rublius_bought' => (bool)($rublius['bought'] ?? false),
            'prognobaks_available' => $shopOpen && (bool)($prognobaks['available'] ?? false),
            'rublius_available' => $shopOpen && (bool)($rublius['available'] ?? false),
        ];
    }

    private function resolveActiveMilestone(int $userId, int $currentTour): int
    {
        if ($currentTour < GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
            return 0;
        }

        $milestones = GameEconomyConfig::getTreasuryShopMilestonesUpTo($currentTour);
        if (!$milestones) {
            return 0;
        }

        $chainOk = true;
        $active = 0;

        foreach ($milestones as $milestone) {
            if (!$chainOk) {
                break;
            }

            $active = $milestone;

            if ($milestone === GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
                continue;
            }

            $prev = $milestone - GameEconomyConfig::TREASURY_SHOP_MILESTONE_STEP;
            $prevWave = $this->repository->getTreasuryShopWave($userId, $prev);
            $chainOk = $prevWave
                && $this->isTruthy($prevWave['UF_PROGNOBAKS_BOUGHT'] ?? false)
                && $this->isTruthy($prevWave['UF_RUBLIUS_BOUGHT'] ?? false);
        }

        return $chainOk ? $active : 0;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildOffers(int $userId, int $milestone, int $currentTour): array
    {
        $wave = $this->ensureWaveRow($userId, $milestone);
        $pBought = $this->isTruthy($wave['UF_PROGNOBAKS_BOUGHT'] ?? false);
        $rBought = $this->isTruthy($wave['UF_RUBLIUS_BOUGHT'] ?? false);
        $premium1dBought = $this->isTruthy($wave['UF_PREMIUM_BOUGHT'] ?? false);
        $premium3dBought = $this->isTruthy($wave['UF_PREMIUM_3D_BOUGHT'] ?? false);
        $premium5dBought = $this->isTruthy($wave['UF_PREMIUM_5D_BOUGHT'] ?? false);
        $extendedPremium = $milestone >= GameEconomyConfig::TREASURY_SHOP_PREMIUM_EXTENDED_MILESTONE;

        return [
            'prognobaks_chest' => [
                'key' => 'prognobaks_chest',
                'label' => 'Сундук ЧМ-26',
                'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'bought' => $pBought,
                'available' => !$pBought,
            ],
            'rublius_chest' => [
                'key' => 'rublius_chest',
                'label' => 'Сундук ЧМ-26',
                'price' => GameEconomyConfig::TREASURY_SHOP_CHEST_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $rBought,
                'available' => !$rBought,
            ],
            'premium_1d' => [
                'key' => 'premium_1d',
                'label' => 'Премиум 1 сутки',
                'days' => 1,
                'emoji' => '📜',
                'price' => GameEconomyConfig::TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $premium1dBought,
                'available' => !$premium1dBought,
            ],
            'premium_3d' => [
                'key' => 'premium_3d',
                'label' => 'Премиум 3 суток',
                'days' => 3,
                'emoji' => '📜',
                'price' => GameEconomyConfig::TREASURY_SHOP_PREMIUM_3D_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $premium3dBought,
                'available' => $extendedPremium && !$premium3dBought,
                'coming_soon' => !$extendedPremium,
            ],
            'premium_5d' => [
                'key' => 'premium_5d',
                'label' => 'Премиум 5 суток',
                'days' => 5,
                'emoji' => '📜',
                'price' => GameEconomyConfig::TREASURY_SHOP_PREMIUM_5D_RUBLIUS_PRICE,
                'currency' => GameEconomyConfig::CURRENCY_RUBLIUS,
                'bought' => $premium5dBought,
                'available' => $extendedPremium && !$premium5dBought,
                'coming_soon' => !$extendedPremium,
            ],
        ];
    }

    private function getPremiumBoughtField(string $offerKey): string
    {
        $map = [
            'premium_1d' => 'UF_PREMIUM_BOUGHT',
            'premium' => 'UF_PREMIUM_BOUGHT',
            'premium_3d' => 'UF_PREMIUM_3D_BOUGHT',
            'premium_5d' => 'UF_PREMIUM_5D_BOUGHT',
        ];

        if (!isset($map[$offerKey])) {
            throw new \InvalidArgumentException('Некорректное предложение премиума');
        }

        return $map[$offerKey];
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
}
