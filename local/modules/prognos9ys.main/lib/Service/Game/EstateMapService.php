<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\CityRepository;

class EstateMapService
{
    private CityRepository $cityRepository;
    private TreasuryCityService $treasuryCityService;

    public function __construct(
        ?CityRepository $cityRepository = null,
        ?TreasuryCityService $treasuryCityService = null
    ) {
        $this->cityRepository = $cityRepository ?? new CityRepository();
        $this->treasuryCityService = $treasuryCityService ?? new TreasuryCityService(
            $this->cityRepository
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorldMapState(int $userId = 0): array
    {
        $dbCities = $this->cityRepository->getAllCitiesIndexedBySlug();
        $userPlots = $userId > 0 ? $this->getUserPlotsIndexed($userId) : [];
        $groups = [];

        foreach (EstateWorldMapConfig::groups() as $groupId => $slugs) {
            $cities = [];
            foreach ($slugs as $slug) {
                $meta = EstateCityConfig::all()[$slug] ?? null;
                if ($meta === null) {
                    continue;
                }

                $row = $dbCities[$slug] ?? null;
                $status = $row
                    ? (string)($row['UF_STATUS'] ?? EstateCityConfig::STATUS_FOUNDING)
                    : EstateCityConfig::STATUS_PLANNED;

                $cityId = (int)($row['ID'] ?? 0);
                $plotsClaimed = $cityId > 0 ? $this->cityRepository->countClaimedPlots($cityId) : 0;
                $userPlot = $userPlots[$slug] ?? null;

                $cities[] = [
                    'slug' => $slug,
                    'city_name' => $meta['city_name'],
                    'country_label' => $meta['country_label'],
                    'status' => $status,
                    'on_map' => $status !== EstateCityConfig::STATUS_PLANNED,
                    'is_open' => $status === EstateCityConfig::STATUS_OPEN,
                    'plots_claimed' => $plotsClaimed,
                    'plots_total' => EstateCityConfig::TOTAL_PLOTS,
                    'user_plot_number' => $userPlot,
                ];
            }

            $groups[] = [
                'id' => $groupId,
                'cities' => $cities,
            ];
        }

        $catalog = $this->treasuryCityService->getCatalog();

        return [
            'groups' => $groups,
            'founded_count' => $catalog['founded_count'],
            'open_count' => $catalog['open_count'],
            'total_cities' => count(EstateCityConfig::all()),
            'layout' => 'wc26_groups_12x4',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCityStreetMap(string $slug, int $userId = 0): array
    {
        $slug = strtolower(trim($slug));
        if (!EstateCityConfig::hasCity($slug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        $meta = EstateCityConfig::all()[$slug];
        $row = $this->cityRepository->getCityBySlug($slug);
        $status = $row
            ? (string)($row['UF_STATUS'] ?? EstateCityConfig::STATUS_FOUNDING)
            : EstateCityConfig::STATUS_PLANNED;
        $cityId = (int)($row['ID'] ?? 0);

        if ($cityId > 0) {
            $this->cityRepository->ensureCityPlots($cityId);
        }

        $plotsByNumber = [];
        foreach ($cityId > 0 ? $this->cityRepository->getPlotsByCityId($cityId) : [] as $plotRow) {
            $num = (int)($plotRow['UF_PLOT_NUMBER'] ?? 0);
            if ($num > 0) {
                $plotsByNumber[$num] = $plotRow;
            }
        }

        $plots = [];
        for ($num = 1; $num <= EstateCityConfig::TOTAL_PLOTS; $num++) {
            $plotRow = $plotsByNumber[$num] ?? null;
            $ownerId = (int)($plotRow['UF_OWNER_USER_ID'] ?? 0);
            $plots[] = [
                'number' => $num,
                'side' => EstateCityConfig::plotSide($num),
                'claimed' => $ownerId > 0,
                'is_mine' => $userId > 0 && $ownerId === $userId,
            ];
        }

        $buildings = [];
        if ($row) {
            foreach ($this->treasuryCityService->getCatalog()['cities'] as $cityRow) {
                if ((string)($cityRow['slug'] ?? '') === $slug) {
                    foreach ((array)($cityRow['buildings'] ?? []) as $building) {
                        $buildings[] = EstateBuildingRecipeBridge::enrichBuildingRow($building);
                    }
                    break;
                }
            }
        } else {
            foreach (EstateCityConfig::FOUNDING_BUILDINGS as $recipeCode) {
                $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
                if ($recipe === null) {
                    continue;
                }
                $bom = (array)($recipe['components'] ?? []);
                $buildings[] = EstateBuildingRecipeBridge::enrichBuildingRow([
                    'recipe_code' => $recipeCode,
                    'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? $recipeCode),
                    'status' => 'planned',
                    'needed' => $bom,
                    'remaining' => $bom,
                    'progress_pct' => 0.0,
                    'opens_city_map' => !empty($recipe['opens_city_map']),
                ]);
            }
        }

        $estateProjects = [];
        foreach (['estate_fence_1', 'estate_house_1'] as $projectCode) {
            $recipe = EstateRecipesConfig::all()[$projectCode] ?? null;
            if ($recipe === null) {
                continue;
            }
            $components = (array)($recipe['components'] ?? []);
            $estateProjects[] = [
                'code' => $projectCode,
                'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? $projectCode),
                'kind' => (string)($recipe['kind'] ?? 'player_estate'),
                'components' => EstateBuildingRecipeBridge::formatComponentList($components),
                'nominal_total' => (float)($recipe['nominal_total'] ?? 0),
            ];
        }

        return [
            'slug' => $slug,
            'city_name' => $meta['city_name'],
            'country_label' => $meta['country_label'],
            'status' => $status,
            'on_map' => $status !== EstateCityConfig::STATUS_PLANNED,
            'is_open' => $status === EstateCityConfig::STATUS_OPEN,
            'plots' => $plots,
            'plots_odd' => array_values(array_filter($plots, static fn(array $p): bool => $p['side'] === 'odd')),
            'plots_even' => array_values(array_filter($plots, static fn(array $p): bool => $p['side'] === 'even')),
            'civic' => [
                ['code' => 'civic_exchange_branch', 'label' => 'Биржа', 'slot' => 'center_top_left'],
                ['code' => 'civic_bank_branch', 'label' => 'Банк', 'slot' => 'center_top_right'],
                ['code' => 'civic_city_hall', 'label' => 'Управа', 'slot' => 'center_bottom'],
            ],
            'buildings' => $buildings,
            'estate_projects' => $estateProjects,
        ];
    }

    /**
     * @return array<string, int> slug => plot number
     */
    private function getUserPlotsIndexed(int $userId): array
    {
        $result = [];
        foreach ($this->cityRepository->getAllCitiesIndexedBySlug() as $slug => $cityRow) {
            $cityId = (int)($cityRow['ID'] ?? 0);
            if ($cityId <= 0) {
                continue;
            }
            foreach ($this->cityRepository->getPlotsByCityId($cityId) as $plotRow) {
                if ((int)($plotRow['UF_OWNER_USER_ID'] ?? 0) === $userId) {
                    $num = (int)($plotRow['UF_PLOT_NUMBER'] ?? 0);
                    if ($num > 0) {
                        $result[$slug] = $num;
                    }
                }
            }
        }

        return $result;
    }
}
