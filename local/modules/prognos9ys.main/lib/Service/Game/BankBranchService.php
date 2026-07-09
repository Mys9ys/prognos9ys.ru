<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankBranchService
{
    private const RECIPE_BANK_BRANCH = 'civic_bank_branch';
    private const REF_TYPE = 'bank_branch_presence';

    private GameEconomyRepository $repository;
    private TreasuryCityService $cityService;
    private WalletService $walletService;
    private TreasuryService $treasuryService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?TreasuryCityService $cityService = null,
        ?WalletService $walletService = null,
        ?TreasuryService $treasuryService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->cityService = $cityService ?? new TreasuryCityService();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->treasuryService = $treasuryService ?? new TreasuryService($this->repository);
    }

    /**
     * @return array<int, array{slug:string,city_name:string,fee:float}>
     */
    public function getOpenOpportunities(array $bank): array
    {
        $opened = $this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '');
        $opportunities = [];

        foreach (EstateCityConfig::all() as $slug => $meta) {
            if (in_array($slug, $opened, true)) {
                continue;
            }

            if (!in_array($slug, $this->cityService->listCitySlugsWithCompleteBankBranch(), true)) {
                continue;
            }

            $opportunities[] = [
                'slug' => $slug,
                'city_name' => (string)($meta['city_name'] ?? EstateCityConfig::getCityName($slug)),
                'fee' => EstateCityConfig::BRANCH_PRESENCE_FEE,
            ];
        }

        usort($opportunities, static function (array $a, array $b): int {
            return strcasecmp((string)($a['city_name'] ?? ''), (string)($b['city_name'] ?? ''));
        });

        return $opportunities;
    }

    /**
     * @return array<int, array{slug:string,city_name:string}>
     */
    public function formatOpenedBranches(array $bank): array
    {
        $branches = [];
        foreach ($this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '') as $slug) {
            $branches[] = [
                'slug' => $slug,
                'city_name' => EstateCityConfig::getCityName($slug),
            ];
        }

        return $branches;
    }

    /**
     * @return array<string, mixed>
     */
    public function openBranch(int $userId, string $citySlug): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $citySlug = strtolower(trim($citySlug));
        if (!EstateCityConfig::hasCity($citySlug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        $bank = $this->repository->getUserBankByOwnerId($userId);
        if (!$bank) {
            throw new \RuntimeException('Сначала откройте банк');
        }

        $bankId = (int)$bank['ID'];
        $opened = $this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '');
        if (in_array($citySlug, $opened, true)) {
            throw new \RuntimeException('Филиал в этом городе уже открыт');
        }

        if (!$this->cityService->isCivicBuildingComplete($citySlug, self::RECIPE_BANK_BRANCH)) {
            throw new \RuntimeException('В городе ещё не построен филиал банка');
        }

        $fee = EstateCityConfig::BRANCH_PRESENCE_FEE;
        $wallet = $this->walletService->getWalletSummary($userId);
        if (round((float)($wallet['prognobaks'] ?? 0), 1) < $fee) {
            throw new \RuntimeException('Недостаточно прогнобаксов для прописки филиала (' . $fee . ' 🪙)');
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $fee,
            'bank_branch_presence',
            self::REF_TYPE . ':' . $citySlug,
            $bankId
        );

        $this->treasuryService->credit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $fee,
            'bank_branch_presence',
            $bankId,
            $userId,
            self::REF_TYPE . ':' . $citySlug
        );

        $opened[] = $citySlug;
        $this->repository->updateUserBank($bankId, [
            'UF_BRANCH_CITIES' => $this->encodeBranchCities($opened),
        ]);

        $updatedBank = $this->repository->getUserBankById($bankId);

        return [
            'bank_id' => $bankId,
            'city_slug' => $citySlug,
            'city_name' => EstateCityConfig::getCityName($citySlug),
            'fee' => $fee,
            'branches' => $this->formatOpenedBranches($updatedBank ?? $bank),
            'branch_opportunities' => $this->getOpenOpportunities($updatedBank ?? $bank),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listBranchesInCity(string $citySlug): array
    {
        $citySlug = strtolower(trim($citySlug));
        if (!EstateCityConfig::hasCity($citySlug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        $buildingComplete = $this->cityService->isCivicBuildingComplete($citySlug, self::RECIPE_BANK_BRANCH);
        $branches = [];
        $pendingCount = 0;

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            $bankId = (int)$bank['ID'];
            $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
            $opened = $this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '');

            if (in_array($citySlug, $opened, true)) {
                $branches[] = [
                    'bank_id' => $bankId,
                    'owner_id' => $ownerId,
                    'owner_name' => $this->resolveUserName($ownerId),
                ];
                continue;
            }

            if ($buildingComplete) {
                $pendingCount++;
            }
        }

        usort($branches, static function (array $a, array $b): int {
            return strcasecmp((string)($a['owner_name'] ?? ''), (string)($b['owner_name'] ?? ''));
        });

        return [
            'city_slug' => $citySlug,
            'city_name' => EstateCityConfig::getCityName($citySlug),
            'building_complete' => $buildingComplete,
            'branches' => $branches,
            'pending_count' => $pendingCount,
            'fee' => EstateCityConfig::BRANCH_PRESENCE_FEE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminOpenAllBranchesInCity(int $adminUserId, string $citySlug): array
    {
        if ($adminUserId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $citySlug = strtolower(trim($citySlug));
        if (!EstateCityConfig::hasCity($citySlug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        if (!$this->cityService->isCivicBuildingComplete($citySlug, self::RECIPE_BANK_BRANCH)) {
            throw new \RuntimeException('В городе ещё не построен филиал банка');
        }

        $opened = [];
        $skipped = 0;
        $failed = [];

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            $bankId = (int)$bank['ID'];
            $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
            $branchCities = $this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '');

            if (in_array($citySlug, $branchCities, true)) {
                $skipped++;
                continue;
            }

            try {
                $this->registerBranchWithoutPayment($bank, $citySlug);
                $opened[] = [
                    'bank_id' => $bankId,
                    'owner_id' => $ownerId,
                    'owner_name' => $this->resolveUserName($ownerId),
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'bank_id' => $bankId,
                    'owner_id' => $ownerId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $cityState = $this->listBranchesInCity($citySlug);

        return [
            'city_slug' => $citySlug,
            'city_name' => $cityState['city_name'],
            'opened_count' => count($opened),
            'opened' => $opened,
            'skipped_count' => $skipped,
            'failed' => $failed,
            'branches' => $cityState['branches'],
            'pending_count' => $cityState['pending_count'],
        ];
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    public function parseBranchCities($raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(static function ($slug): string {
                return strtolower(trim((string)$slug));
            }, $decoded))));
        }

        return array_values(array_unique(array_filter(array_map(static function (string $slug): string {
            return strtolower(trim($slug));
        }, explode(',', $raw)))));
    }

    /**
     * @param string[] $slugs
     */
    public function encodeBranchCities(array $slugs): string
    {
        $normalized = [];
        foreach ($slugs as $slug) {
            $slug = strtolower(trim((string)$slug));
            if ($slug !== '') {
                $normalized[$slug] = $slug;
            }
        }

        return implode(',', array_values($normalized));
    }

    /**
     * @param array<string, mixed> $bank
     */
    private function registerBranchWithoutPayment(array $bank, string $citySlug): void
    {
        $bankId = (int)($bank['ID'] ?? 0);
        if ($bankId <= 0) {
            throw new \RuntimeException('Некорректный банк');
        }

        $citySlug = strtolower(trim($citySlug));
        $opened = $this->parseBranchCities($bank['UF_BRANCH_CITIES'] ?? '');
        if (in_array($citySlug, $opened, true)) {
            return;
        }

        $opened[] = $citySlug;
        $this->repository->updateUserBank($bankId, [
            'UF_BRANCH_CITIES' => $this->encodeBranchCities($opened),
        ]);
    }

    private function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return 'user#' . $userId;
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($row['LOGIN'] ?? ('user#' . $userId));
    }
}
