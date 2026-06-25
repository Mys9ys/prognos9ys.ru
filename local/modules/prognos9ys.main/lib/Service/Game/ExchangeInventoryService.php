<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Списание и выдача предметов при лотах биржи.
 */
class ExchangeInventoryService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = new GameEventScopeService();
    }

    public function getAvailableQty(int $userId, string $kind, string $code, string $category = '', int $eventId = 0): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($kind === ExchangeConfig::KIND_CHEST) {
            return $this->repository->countClosedChestUnitsByType($userId, $code);
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            $days = (int)$code;
            $breakdown = $this->repository->getPremiumScrollBreakdownForUser($userId);

            return (int)($breakdown[$days] ?? 0);
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            $counts = $this->repository->getPennantInventoryCountsForUser($userId);

            return (int)($counts[$code] ?? 0);
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            return $this->repository->getLootItemCount($userId, $eventId, $code, $category);
        }

        return 0;
    }

    public function takeFromSeller(
        int $userId,
        string $kind,
        string $code,
        string $category,
        int $eventId,
        int $qty
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Некорректное количество');
        }

        $available = $this->getAvailableQty($userId, $kind, $code, $category, $eventId);
        if ($available < $qty) {
            throw new \RuntimeException('Недостаточно предметов в инвентаре');
        }

        if ($kind === ExchangeConfig::KIND_CHEST) {
            $this->repository->consumeClosedChestUnitsByType($userId, $code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            $this->repository->consumePremiumScrollUnits($userId, (int)$code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            $this->repository->consumePennantUnits($userId, $code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            $this->repository->decrementLootItem($userId, $eventId, $code, $qty);

            return;
        }

        throw new \InvalidArgumentException('Неизвестный тип предмета');
    }

    public function giveToBuyer(
        int $userId,
        string $kind,
        string $code,
        string $category,
        int $eventId,
        string $teamCode,
        int $qty
    ): void {
        if ($qty <= 0) {
            return;
        }

        if ($kind === ExchangeConfig::KIND_CHEST) {
            $this->repository->grantClosedChestUnits($userId, $code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            $this->repository->grantPremiumScrollUnits($userId, (int)$code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            $this->repository->grantPennantUnits($userId, $code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            $sealed = $category === ChestLootConfig::CATEGORY_PACK ? 'Y' : 'N';
            $this->repository->incrementLootItem($userId, $eventId, $code, $category, $qty, $sealed);

            return;
        }
    }

    public function resolveNominal(
        string $kind,
        string $code,
        string $category = '',
        ?string $teamCode = null
    ): float {
        if ($kind === ExchangeConfig::KIND_CHEST) {
            return ExchangeNominalConfig::getChestNominal($code);
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            return ExchangeNominalConfig::getPremiumScrollNominal((int)$code);
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            return ExchangeNominalConfig::getPennantNominal($code);
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            return ExchangeNominalConfig::getLootNominal($code, $category, $teamCode);
        }

        return 0.0;
    }

    public function buildItemLabel(
        string $kind,
        string $code,
        string $category = '',
        ?string $teamCode = null
    ): string {
        if ($kind === ExchangeConfig::KIND_CHEST) {
            $map = [
                TreasureService::CHEST_TYPE_LEVEL => 'Сундук за уровень',
                TreasureService::CHEST_TYPE_ACHIEVEMENT => 'Сундук за ачивку',
                TreasureService::CHEST_TYPE_MATCH => 'Сундук ЧМ (матч)',
                TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT => 'Сундук ЧМ (ачивка)',
                TreasureService::CHEST_TYPE_SHOP_WC26 => 'Сундук ЧМ (лавка)',
            ];

            return $map[$code] ?? 'Сундук';
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            return 'Свиток премиума ' . (int)$code . 'д';
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            return $code === 'chm2026' ? 'Вымпел ЧМ-26' : 'Вымпел сайта';
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($teamCode) {
                $base = ExchangeNominalConfig::getLootNominal($code, $category, $teamCode);

                return ChestLootConfig::getLabel($code) . ' (' . $teamCode . ', ~' . $base . '🪙)';
            }

            return ChestLootConfig::getLabel($code);
        }

        return $code;
    }
}
