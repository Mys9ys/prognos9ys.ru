<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\CityRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class TreasuryCityService
{
    /** Порядок блоков на бирже: управа → банк → биржа. */
    private const EXCHANGE_BUILDING_ORDER = [
        'civic_city_hall' => 0,
        'civic_bank_branch' => 1,
        'civic_exchange_branch' => 2,
    ];
    private const BANK_BRANCH_PRESENCE_REF_TYPE = 'city_bank_branch_presence';

    /** @var string[]|null */
    private static $completeBankBranchCitySlugs = null;

    private CityRepository $cityRepository;
    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;

    public function __construct(
        ?CityRepository $cityRepository = null,
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null
    ) {
        $this->cityRepository = $cityRepository ?? new CityRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
    }

    /**
     * @return array{
     *   cities: array<int, array<string, mixed>>,
     *   founded_count: int,
     *   open_count: int
     * }
     */
    public function getCatalog(): array
    {
        $dbCities = $this->cityRepository->getAllCitiesIndexedBySlug();
        $cities = [];
        $foundedCount = 0;
        $openCount = 0;

        foreach (EstateCityConfig::all() as $slug => $meta) {
            $row = $dbCities[$slug] ?? null;
            $status = $row
                ? (string)($row['UF_STATUS'] ?? EstateCityConfig::STATUS_FOUNDING)
                : EstateCityConfig::STATUS_PLANNED;

            if ($status !== EstateCityConfig::STATUS_PLANNED) {
                $foundedCount++;
            }
            if ($status === EstateCityConfig::STATUS_OPEN) {
                $openCount++;
            }

            $cityId = (int)($row['ID'] ?? 0);
            if ($cityId > 0) {
                $this->cityRepository->ensureCityPlots($cityId);
            }
            $cities[] = [
                'slug' => $slug,
                'city_name' => $meta['city_name'],
                'country_label' => $meta['country_label'],
                'status' => $status,
                'founded_at' => $this->formatDateTime($row['UF_FOUNDED_AT'] ?? null),
                'opened_at' => $this->formatDateTime($row['UF_OPENED_AT'] ?? null),
                'buildings' => $row ? $this->buildBuildingRows($slug) : [],
                'plots_claimed' => $cityId > 0 ? $this->cityRepository->countClaimedPlots($cityId) : 0,
                'plots_total' => EstateCityConfig::TOTAL_PLOTS,
            ];
        }

        return [
            'cities' => $cities,
            'founded_count' => $foundedCount,
            'open_count' => $openCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startFounding(string $slug, int $adminUserId): array
    {
        $slug = strtolower(trim($slug));
        if (!EstateCityConfig::hasCity($slug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        if ($this->cityRepository->getCityBySlug($slug)) {
            throw new \RuntimeException('Город уже основан');
        }

        $cityId = $this->cityRepository->createCity($slug, $adminUserId);
        $this->cityRepository->ensureCityPlots($cityId);

        foreach (EstateCityConfig::FOUNDING_BUILDINGS as $recipeCode) {
            $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
            if ($recipe === null) {
                continue;
            }

            $this->professionRepository->ensureCityConstructionProject(
                $slug,
                $recipeCode,
                (string)($recipe['kind'] ?? 'civic_city')
            );
        }

        return $this->getCityDetail($slug);
    }

    public function isCivicBuildingComplete(string $citySlug, string $recipeCode): bool
    {
        $citySlug = strtolower(trim($citySlug));
        $recipeCode = trim($recipeCode);
        if ($citySlug === '' || $recipeCode === '') {
            return false;
        }

        foreach ($this->professionRepository->getConstructionProjectsByCity($citySlug) as $project) {
            if ((string)($project['UF_RECIPE_CODE'] ?? '') !== $recipeCode) {
                continue;
            }

            $status = (string)($project['UF_STATUS'] ?? 'building');
            if ($status === 'complete') {
                return true;
            }

            $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
            if ($recipe === null) {
                return false;
            }

            $bom = (array)($recipe['components'] ?? []);
            $stash = $this->professionRepository->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');

            return $this->calcRemaining($bom, $stash) === [];
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function listCitySlugsWithCompleteBankBranch(): array
    {
        if (self::$completeBankBranchCitySlugs !== null) {
            return self::$completeBankBranchCitySlugs;
        }

        $slugs = [];
        foreach ($this->professionRepository->getCompleteCityConstructionProjectsByRecipe('civic_bank_branch') as $project) {
            $slug = strtolower(trim((string)($project['UF_CITY_SLUG'] ?? '')));
            if ($slug !== '') {
                $slugs[$slug] = $slug;
            }
        }

        self::$completeBankBranchCitySlugs = array_values($slugs);

        return self::$completeBankBranchCitySlugs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBuildOrdersForExchange(int $userId = 0): array
    {
        $orders = [];
        $dbCities = $this->cityRepository->getAllCitiesIndexedBySlug();

        foreach ($this->professionRepository->getActiveCityConstructionProjects() as $project) {
            $slug = strtolower(trim((string)($project['UF_CITY_SLUG'] ?? '')));
            $recipeCode = (string)($project['UF_RECIPE_CODE'] ?? '');
            if ($slug === '' || $recipeCode === '') {
                continue;
            }

            $cityRow = $dbCities[$slug] ?? null;
            if (!$cityRow || (string)($cityRow['UF_STATUS'] ?? '') === EstateCityConfig::STATUS_PLANNED) {
                continue;
            }

            $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
            if ($recipe === null) {
                continue;
            }

            $bom = (array)($recipe['components'] ?? []);
            if ($bom === []) {
                continue;
            }

            $stash = $this->professionRepository->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');
            $remaining = $this->calcRemaining($bom, $stash);
            if ($remaining === []) {
                continue;
            }

            $orders[] = [
                'project_id' => (int)$project['ID'],
                'city_slug' => $slug,
                'city_name' => EstateCityConfig::getCityName($slug),
                'country_label' => EstateCityConfig::getCountryLabel($slug),
                'recipe_code' => $recipeCode,
                'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? $recipeCode),
                'stash' => $stash,
                'needed' => $bom,
                'remaining' => $remaining,
                'remaining_items' => $this->formatRemainingItems($remaining, $userId),
                'progress_pct' => $this->calcProgressPct($bom, $stash),
                'nominal_total' => (float)($recipe['nominal_total'] ?? 0),
            ];
        }

        usort($orders, static function (array $a, array $b): int {
            $cityCmp = strcmp((string)$a['city_name'], (string)$b['city_name']);
            if ($cityCmp !== 0) {
                return $cityCmp;
            }

            $orderA = self::EXCHANGE_BUILDING_ORDER[(string)($a['recipe_code'] ?? '')] ?? 99;
            $orderB = self::EXCHANGE_BUILDING_ORDER[(string)($b['recipe_code'] ?? '')] ?? 99;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp((string)$a['recipe_code'], (string)$b['recipe_code']);
        });

        return $orders;
    }

    /**
     * @return array<string, mixed>
     */
    public function donateComponent(
        int $userId,
        string $citySlug,
        string $recipeCode,
        string $componentCode,
        int $qty
    ): array {
        $citySlug = strtolower(trim($citySlug));
        $recipeCode = trim($recipeCode);
        $componentCode = trim($componentCode);
        $qty = (int)$qty;

        if ($userId <= 0 || $citySlug === '' || $recipeCode === '' || $componentCode === '' || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры');
        }

        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city) {
            throw new \RuntimeException('Город не основан');
        }

        $status = (string)($city['UF_STATUS'] ?? '');
        if ($status === EstateCityConfig::STATUS_PLANNED) {
            throw new \RuntimeException('Город ещё не в стройке');
        }

        if (!in_array($recipeCode, EstateCityConfig::FOUNDING_BUILDINGS, true)) {
            throw new \InvalidArgumentException('Неизвестное госздание');
        }

        $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
        if ($recipe === null) {
            throw new \InvalidArgumentException('Рецепт не найден');
        }

        $bom = (array)($recipe['components'] ?? []);
        if (!isset($bom[$componentCode])) {
            throw new \InvalidArgumentException('Компонент не входит в проект');
        }

        $project = $this->professionRepository->getCityConstructionProject($citySlug, $recipeCode);
        if (!$project) {
            throw new \RuntimeException('Проект стройки не найден');
        }

        if ((string)($project['UF_STATUS'] ?? '') === 'complete') {
            throw new \RuntimeException('Здание уже сдано');
        }

        $stash = $this->professionRepository->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');
        $remaining = $this->calcRemaining($bom, $stash);
        $needLeft = (int)($remaining[$componentCode] ?? 0);
        if ($needLeft <= 0) {
            throw new \RuntimeException('Компонент уже собран полностью');
        }

        $donateQty = min($qty, $needLeft);
        $userQty = $this->professionRepository->getUserMaterialQty($userId, $componentCode);
        if ($userQty < $donateQty) {
            throw new \RuntimeException('Недостаточно компонентов в инвентаре');
        }

        $this->professionRepository->consumeUserMaterialQty($userId, $componentCode, $donateQty);
        $stash[$componentCode] = (int)($stash[$componentCode] ?? 0) + $donateQty;

        $paidTotal = EstateRecipesConfig::calcComponentDonationPayout($componentCode, $donateQty);
        if ($paidTotal > 0) {
            (new WalletService())->credit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $paidTotal,
                'city_build_donate',
                'city_build_project',
                (int)$project['ID']
            );
        }

        $now = new DateTime();
        $isComplete = $this->calcRemaining($bom, $stash) === [];
        $this->professionRepository->updateConstructionProject((int)$project['ID'], [
            'UF_STASH_JSON' => $this->professionRepository->encodeStashJson($stash),
            'UF_STATUS' => $isComplete ? 'complete' : 'building',
            'UF_PROGRESS' => $isComplete ? 100 : (int)round($this->calcProgressPct($bom, $stash)),
            'UF_UPDATED_AT' => $now,
        ]);

        if ($isComplete && $recipeCode === 'civic_city_hall' && $status === EstateCityConfig::STATUS_FOUNDING) {
            $this->cityRepository->updateCity((int)$city['ID'], [
                'UF_STATUS' => EstateCityConfig::STATUS_OPEN,
                'UF_OPENED_AT' => $now,
            ]);
        }

        if ($isComplete && $recipeCode === 'civic_bank_branch') {
            self::$completeBankBranchCitySlugs = null;
            $projectId = (int)($project['ID'] ?? 0);
            if ($projectId > 0) {
                $alreadyPaid = $this->economyRepository
                    ->hasTreasuryTxByRef(self::BANK_BRANCH_PRESENCE_REF_TYPE, $projectId);
                if (!$alreadyPaid) {
                    try {
                        (new TreasuryService())->debit(
                            GameEconomyConfig::CURRENCY_PROGNOBAKS,
                            EstateCityConfig::BRANCH_PRESENCE_FEE,
                            'city_bank_branch_presence',
                            $projectId,
                            $userId,
                            self::BANK_BRANCH_PRESENCE_REF_TYPE
                        );
                    } catch (\RuntimeException $exception) {
                        $this->professionRepository->updateConstructionProject((int)$project['ID'], [
                            'UF_STATUS' => 'pending_fee',
                            'UF_UPDATED_AT' => $now,
                        ]);
                        $isComplete = false;
                    }
                }
            }
        }

        return [
            'donated_qty' => $donateQty,
            'component_code' => $componentCode,
            'component_label' => ProfessionCraftedItemConfig::getLabel($componentCode),
            'paid_total' => $paidTotal,
            'paid_per_unit' => EstateRecipesConfig::calcComponentDonationUnitPayout($componentCode),
            'building_complete' => $isComplete,
            'city_opened' => $isComplete && $recipeCode === 'civic_city_hall',
            'order' => $this->formatBuildOrderRow(
                $this->professionRepository->getCityConstructionProject($citySlug, $recipeCode) ?: $project,
                $recipe
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCityDetail(string $slug): array
    {
        $meta = EstateCityConfig::all()[$slug] ?? null;
        $row = $this->cityRepository->getCityBySlug($slug);
        $cityId = (int)($row['ID'] ?? 0);

        return [
            'slug' => $slug,
            'city_name' => $meta['city_name'] ?? EstateCityConfig::getCityName($slug),
            'country_label' => $meta['country_label'] ?? EstateCityConfig::getCountryLabel($slug),
            'status' => (string)($row['UF_STATUS'] ?? EstateCityConfig::STATUS_FOUNDING),
            'founded_at' => $this->formatDateTime($row['UF_FOUNDED_AT'] ?? null),
            'opened_at' => $this->formatDateTime($row['UF_OPENED_AT'] ?? null),
            'buildings' => $this->buildBuildingRows($slug),
            'plots_claimed' => $cityId > 0 ? $this->cityRepository->countClaimedPlots($cityId) : 0,
            'plots_total' => EstateCityConfig::TOTAL_PLOTS,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBuildingRows(string $slug): array
    {
        $rows = [];
        $projectsByRecipe = [];

        foreach ($this->professionRepository->getConstructionProjectsByCity($slug) as $project) {
            $projectsByRecipe[(string)($project['UF_RECIPE_CODE'] ?? '')] = $project;
        }

        foreach (EstateCityConfig::FOUNDING_BUILDINGS as $recipeCode) {
            $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
            if ($recipe === null) {
                continue;
            }

            $project = $projectsByRecipe[$recipeCode] ?? null;
            if ($project) {
                $rows[] = $this->formatBuildOrderRow($project, $recipe);
            } else {
                $bom = (array)($recipe['components'] ?? []);
                $rows[] = [
                    'recipe_code' => $recipeCode,
                    'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? $recipeCode),
                    'status' => 'building',
                    'stash' => [],
                    'needed' => $bom,
                    'remaining' => $bom,
                    'progress_pct' => 0.0,
                    'nominal_total' => (float)($recipe['nominal_total'] ?? 0),
                    'opens_city_map' => !empty($recipe['opens_city_map']),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $project
     * @param array<string, mixed> $recipe
     * @return array<string, mixed>
     */
    private function formatBuildOrderRow(array $project, array $recipe): array
    {
        $bom = (array)($recipe['components'] ?? []);
        $stash = $this->professionRepository->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');
        $status = (string)($project['UF_STATUS'] ?? 'building');
        if ($status !== 'complete' && $this->calcRemaining($bom, $stash) === []) {
            $status = 'complete';
        }

        return [
            'project_id' => (int)($project['ID'] ?? 0),
            'recipe_code' => (string)($project['UF_RECIPE_CODE'] ?? ''),
            'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? ''),
            'status' => $status,
            'stash' => $stash,
            'needed' => $bom,
            'remaining' => $this->calcRemaining($bom, $stash),
            'progress_pct' => $status === 'complete' ? 100.0 : $this->calcProgressPct($bom, $stash),
            'nominal_total' => (float)($recipe['nominal_total'] ?? 0),
            'opens_city_map' => !empty($recipe['opens_city_map']),
        ];
    }

    /**
     * @param array<string, int> $bom
     * @param array<string, int> $stash
     * @return array<string, int>
     */
    private function calcRemaining(array $bom, array $stash): array
    {
        $remaining = [];
        foreach ($bom as $code => $need) {
            $need = (int)$need;
            $have = (int)($stash[$code] ?? 0);
            $left = $need - $have;
            if ($left > 0) {
                $remaining[(string)$code] = $left;
            }
        }

        return $remaining;
    }

    /**
     * @param array<string, int> $bom
     * @param array<string, int> $stash
     */
    private function calcProgressPct(array $bom, array $stash): float
    {
        $totalNeed = 0;
        $totalHave = 0;

        foreach ($bom as $code => $need) {
            $need = (int)$need;
            if ($need <= 0) {
                continue;
            }
            $totalNeed += $need;
            $totalHave += min($need, (int)($stash[$code] ?? 0));
        }

        if ($totalNeed <= 0) {
            return 0.0;
        }

        return round(100.0 * $totalHave / $totalNeed, 1);
    }

    /**
     * @param array<string, int> $remaining
     * @return array<int, array{code:string,label:string,qty:int,nominal_per_unit:float,user_have:int}>
     */
    private function formatRemainingItems(array $remaining, int $userId = 0): array
    {
        $items = [];
        foreach ($remaining as $code => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                continue;
            }
            $code = (string)$code;
            $craft = EstateBuildingRecipeBridge::resolveComponentCraft($code);
            $items[] = array_merge([
                'code' => $code,
                'label' => ProfessionCraftedItemConfig::getLabel($code),
                'qty' => $qty,
                'nominal_per_unit' => EstateRecipesConfig::calcComponentDonationUnitPayout($code),
                'user_have' => $userId > 0
                    ? $this->professionRepository->getUserMaterialQty($userId, $code)
                    : 0,
            ], $craft);
        }

        return $items;
    }

    private function formatDateTime($value): ?string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i');
        }

        return null;
    }
}
