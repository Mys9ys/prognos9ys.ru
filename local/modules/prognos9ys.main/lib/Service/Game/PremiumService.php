<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class PremiumService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function hasActivePremium(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $until = $this->repository->getPremiumUntil($userId);

            return $until !== null && $until->getTimestamp() > time();
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * @param int[] $userIds
     * @return array<int, bool>
     */
    public function batchHasActivePremium(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }

        $map = [];
        foreach ($userIds as $userId) {
            $map[$userId] = false;
        }

        try {
            $untilMap = $this->repository->getPremiumUntilMap($userIds);
            $now = time();
            foreach ($untilMap as $userId => $until) {
                if ($until instanceof DateTime && $until->getTimestamp() > $now) {
                    $map[$userId] = true;
                }
            }
        } catch (\Throwable $exception) {
            return $map;
        }

        return $map;
    }

    /**
     * @return array{
     *   active:bool,
     *   until:?string,
     *   remaining_seconds:int,
     *   scrolls:array{1:int,3:int,5:int},
     *   scrolls_total:int
     * }
     */
    public function getSummary(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptySummary();
        }

        try {
            $this->repository->ensurePremiumWalletSchema();
        } catch (\Throwable $exception) {
            return $this->emptySummary();
        }
        $until = $this->repository->getPremiumUntil($userId);
        $now = time();
        $active = $until !== null && $until->getTimestamp() > $now;
        $remaining = $active ? max(0, $until->getTimestamp() - $now) : 0;
        $scrolls = (new TreasureService($this->repository))->getTreasureSummary($userId);

        $breakdown = [
            1 => (int)($scrolls['premium_scrolls_1d'] ?? 0),
            3 => (int)($scrolls['premium_scrolls_3d'] ?? 0),
            5 => (int)($scrolls['premium_scrolls_5d'] ?? 0),
        ];

        return [
            'active' => $active,
            'until' => $active ? $until->format('Y-m-d H:i:s') : null,
            'remaining_seconds' => $remaining,
            'scrolls' => $breakdown,
            'scrolls_total' => (int)($scrolls['premium_scrolls'] ?? 0),
        ];
    }

    /**
     * @return array{activated_days:int,scrolls_used:int,premium:array<string,mixed>,lines:array<int,string>}
     */
    public function activateScrolls(int $userId, int $days = 0, bool $activateAll = false, int $qty = 0): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $this->repository->ensurePremiumWalletSchema();
        $breakdown = $this->repository->getPremiumScrollBreakdownForUser($userId);

        if ($days > 0 && !in_array($days, PremiumEconomyConfig::SCROLL_DAYS, true)) {
            throw new \InvalidArgumentException('Некорректная длительность свитка');
        }

        $scrollsUsed = 0;
        $activatedDays = 0;

        if ($days > 0) {
            $available = (int)($breakdown[$days] ?? 0);
            if ($available <= 0) {
                throw new \RuntimeException('Нет свитков на ' . $days . ' сут.');
            }

            if ($qty <= 0) {
                $qty = $activateAll ? min($available, 30) : 1;
            } else {
                $qty = max(1, min($qty, $available, 30));
            }

            $this->repository->consumePremiumScrollUnits($userId, $days, $qty);
            $scrollsUsed = $qty;
            $activatedDays = $days * $qty;
        } elseif ($activateAll) {
            foreach (PremiumEconomyConfig::SCROLL_DAYS as $scrollDays) {
                $available = (int)($breakdown[$scrollDays] ?? 0);
                if ($available <= 0) {
                    continue;
                }

                $this->repository->consumePremiumScrollUnits($userId, $scrollDays, $available);
                $scrollsUsed += $available;
                $activatedDays += $scrollDays * $available;
            }

            if ($scrollsUsed <= 0) {
                throw new \RuntimeException('Нет свитков премиума в инвентаре');
            }
        } else {
            $consumed = $this->repository->consumeOldestPremiumScrollUnit($userId);
            if ($consumed <= 0) {
                throw new \RuntimeException('Нет свитков премиума в инвентаре');
            }

            $scrollsUsed = 1;
            $activatedDays = $consumed;
        }

        $until = $this->extendPremiumUntil($userId, $activatedDays);
        $lines = [
            'Активировано: +' . $activatedDays . ' сут. (' . $scrollsUsed . ' свит.)',
            'Премиум до: ' . $until->format('d.m.Y H:i'),
        ];

        return [
            'activated_days' => $activatedDays,
            'scrolls_used' => $scrollsUsed,
            'premium' => $this->getSummary($userId),
            'lines' => $lines,
        ];
    }

    public static function resolveSellerCommissionPercent(int $sellerId, ?self $service = null): float
    {
        if ($sellerId <= 0) {
            return PremiumEconomyConfig::COMMISSION_PERCENT_DEFAULT;
        }

        $service = $service ?? new self();

        return $service->hasActivePremium($sellerId)
            ? PremiumEconomyConfig::COMMISSION_PERCENT
            : PremiumEconomyConfig::COMMISSION_PERCENT_DEFAULT;
    }

    /**
     * @param array<int, array<string, mixed>> $listings
     * @return array<int, array<string, mixed>>
     */
    public function sortListingsForCatalog(array $listings): array
    {
        if (count($listings) < 2) {
            return $listings;
        }

        $sellerIds = [];
        foreach ($listings as $row) {
            $sellerId = (int)($row['UF_SELLER_ID'] ?? 0);
            if ($sellerId > 0) {
                $sellerIds[] = $sellerId;
            }
        }

        $premiumMap = $this->batchHasActivePremium($sellerIds);

        usort($listings, static function (array $a, array $b) use ($premiumMap): int {
            $priceA = round((float)($a['UF_PRICE_PER_UNIT'] ?? 0), 1);
            $priceB = round((float)($b['UF_PRICE_PER_UNIT'] ?? 0), 1);
            if ($priceA !== $priceB) {
                return $priceA <=> $priceB;
            }

            $sellerA = (int)($a['UF_SELLER_ID'] ?? 0);
            $sellerB = (int)($b['UF_SELLER_ID'] ?? 0);
            $bankA = (int)($a['UF_SELLER_BANK_ID'] ?? 0) > 0;
            $bankB = (int)($b['UF_SELLER_BANK_ID'] ?? 0) > 0;

            $premA = !$bankA && ($premiumMap[$sellerA] ?? false);
            $premB = !$bankB && ($premiumMap[$sellerB] ?? false);
            if ($premA !== $premB) {
                return $premB <=> $premA;
            }

            $createdA = self::listingCreatedTimestamp($a);
            $createdB = self::listingCreatedTimestamp($b);
            if ($createdA !== $createdB) {
                return $createdA <=> $createdB;
            }

            return ((int)($a['ID'] ?? 0)) <=> ((int)($b['ID'] ?? 0));
        });

        return $listings;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function listingCreatedTimestamp(array $row): int
    {
        $created = $row['UF_CREATED_AT'] ?? null;
        if ($created instanceof DateTime) {
            return $created->getTimestamp();
        }

        if (is_string($created) && $created !== '') {
            $ts = strtotime($created);

            return $ts !== false ? $ts : 0;
        }

        return 0;
    }

    private function extendPremiumUntil(int $userId, int $addDays): DateTime
    {
        if ($addDays <= 0) {
            throw new \InvalidArgumentException('Длительность должна быть положительной');
        }

        $now = new DateTime();
        $current = $this->repository->getPremiumUntil($userId);
        $base = ($current !== null && $current->getTimestamp() > time()) ? $current : $now;
        $until = clone $base;
        $until->add('+' . $addDays . ' days');

        $this->repository->setPremiumUntil($userId, $until);

        return $until;
    }

    /**
     * @return array{
     *   active:bool,
     *   until:?string,
     *   remaining_seconds:int,
     *   scrolls:array{1:int,3:int,5:int},
     *   scrolls_total:int
     * }
     */
    private function emptySummary(): array
    {
        return [
            'active' => false,
            'until' => null,
            'remaining_seconds' => 0,
            'scrolls' => [1 => 0, 3 => 0, 5 => 0],
            'scrolls_total' => 0,
        ];
    }
}
