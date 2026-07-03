<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\AlbumRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

/**
 * Списание и выдача предметов при лотах биржи.
 */
class ExchangeInventoryService
{
    private GameEconomyRepository $repository;
    private ProfessionRepository $professionRepository;
    private GameEventScopeService $scopeService;
    private AlbumRepository $albumRepository;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?ProfessionRepository $professionRepository = null,
        ?AlbumRepository $albumRepository = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->albumRepository = $albumRepository ?? new AlbumRepository();
        $this->scopeService = new GameEventScopeService();
    }

    private function isPremiumMaterialCategory(string $category): bool
    {
        return $category === ExchangeConfig::MATERIAL_CATEGORY_PREMIUM;
    }

    public function getAvailableQty(int $userId, string $kind, string $code, string $category = '', int $eventId = 0): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($kind === ExchangeConfig::KIND_CHEST) {
            if ($code === ExchangeConfig::CHEST_CODE_WC26) {
                $total = 0;
                foreach (ExchangeConfig::wc26LegacyChestTypes() as $chestType) {
                    $total += $this->repository->countClosedChestUnitsByType($userId, $chestType);
                }

                return $total;
            }

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
            if ($this->isSellableAlbum($code, $category)) {
                return $this->getSellableAlbumQty($userId);
            }

            if ($category === ChestLootConfig::CATEGORY_PACK) {
                return $this->repository->getSealedPackCount($userId, $code);
            }

            if (ChestLootConfig::isEventAgnosticLootCategory($category)) {
                return $this->repository->getEventAgnosticLootItemCount($userId, $code, $category);
            }

            return $this->repository->getLootItemCount($userId, $eventId, $code, $category);
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            return $this->professionRepository->getUserMaterialQty(
                $userId,
                $code,
                $this->isPremiumMaterialCategory($category)
            );
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            $wallet = (new WalletService($this->repository))->getWalletSummary($userId);

            return (int)floor((float)($wallet['rublius'] ?? 0));
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
            if ($code === ExchangeConfig::CHEST_CODE_WC26) {
                $remaining = $qty;
                foreach (ExchangeConfig::wc26LegacyChestTypes() as $chestType) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = $this->repository->countClosedChestUnitsByType($userId, $chestType);
                    if ($available <= 0) {
                        continue;
                    }

                    $take = min($available, $remaining);
                    $this->repository->consumeClosedChestUnitsByType($userId, $chestType, $take);
                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw new \RuntimeException('Не удалось списать сундуки');
                }

                return;
            }

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
            if ($this->isSellableAlbum($code, $category)) {
                $this->takeSellableAlbums($userId, $qty);

                return;
            }

            if ($category === ChestLootConfig::CATEGORY_PACK) {
                $this->repository->decrementSealedPack($userId, $code, $qty);

                return;
            }

            if (ChestLootConfig::isEventAgnosticLootCategory($category)) {
                $this->repository->decrementEventAgnosticLootItem($userId, $code, $category, $qty);

                return;
            }

            $this->repository->decrementLootItem($userId, $eventId, $code, $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            $this->professionRepository->consumeUserMaterialQty(
                $userId,
                $code,
                $qty,
                $this->isPremiumMaterialCategory($category)
            );

            return;
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            (new WalletService($this->repository))->debit(
                $userId,
                GameEconomyConfig::CURRENCY_RUBLIUS,
                (float)$qty,
                'exchange_consign',
                'exchange_listing',
                0
            );

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
            $grantType = $code === ExchangeConfig::CHEST_CODE_WC26
                ? TreasureService::CHEST_TYPE_MATCH
                : $code;
            $this->repository->grantClosedChestUnits($userId, $grantType, $qty);

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

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            $this->professionRepository->addUserMaterialQty(
                $userId,
                $code,
                $qty,
                $this->isPremiumMaterialCategory($category)
            );

            return;
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            (new WalletService($this->repository))->credit(
                $userId,
                GameEconomyConfig::CURRENCY_RUBLIUS,
                (float)$qty,
                'exchange_buy',
                'exchange_listing',
                0
            );

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

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            return ExchangeNominalConfig::getMaterialNominal($code);
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            return (float)GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS;
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
            if ($code === ExchangeConfig::CHEST_CODE_WC26
                || in_array($code, ExchangeConfig::wc26LegacyChestTypes(), true)) {
                return 'Сундук ЧМ-26';
            }

            $map = [
                TreasureService::CHEST_TYPE_LEVEL => 'Сундук за уровень',
                TreasureService::CHEST_TYPE_ACHIEVEMENT => 'Сундук за ачивку',
            ];

            return $map[$code] ?? 'Сундук';
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            return 'Свиток премиума ' . (int)$code . 'д';
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            if (AchievementPennantConfig::isAchievementPennantCode($code)) {
                return AchievementPennantConfig::getLabel($code);
            }

            return $code === 'chm2026' ? 'Вымпел ЧМ-26' : 'Вымпел сайта';
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            return 'Рублиус 💎';
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($teamCode) {
                $base = ExchangeNominalConfig::getLootNominal($code, $category, $teamCode);

                return ChestLootConfig::getLabel($code) . ' (' . $teamCode . ', ~' . $base . '🪙)';
            }

            return ChestLootConfig::getLabel($code);
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            $label = ProfessionMaterialConfig::getMaterialLabel($code);
            if ($this->isPremiumMaterialCategory($category)) {
                return $label . ' ★';
            }

            return $label;
        }

        return $code;
    }

    private function isSellableAlbum(string $code, string $category): bool
    {
        return $code === AlbumConfig::ITEM_CODE
            && $category === ChestLootConfig::CATEGORY_ALBUM;
    }

    private function getSellableAlbumQty(int $userId): int
    {
        $lootQty = $this->repository->getEventAgnosticLootItemCount(
            $userId,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM
        );
        $this->albumRepository->ensureSchema();

        return $lootQty + $this->albumRepository->countEmptyAlbumsForUser($userId);
    }

    private function takeSellableAlbums(int $userId, int $qty): void
    {
        $remaining = $qty;
        $lootQty = $this->repository->getEventAgnosticLootItemCount(
            $userId,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM
        );

        if ($lootQty > 0) {
            $take = min($lootQty, $remaining);
            $this->repository->decrementEventAgnosticLootItem(
                $userId,
                AlbumConfig::ITEM_CODE,
                ChestLootConfig::CATEGORY_ALBUM,
                $take
            );
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $this->albumRepository->consumeEmptyAlbumsForUser($userId, $remaining);
        }
    }
}
