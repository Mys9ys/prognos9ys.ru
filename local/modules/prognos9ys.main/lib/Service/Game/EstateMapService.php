<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
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
        $homeEstate = $userId > 0 ? (new HomeEstateService($this->cityRepository))->ensureHomeEstate($userId) : null;
        $regions = [];

        foreach (EstateWorldMapConfig::regions() as $regionId => $regionDef) {
            $cities = [];
            $foundedInRegion = 0;
            $openInRegion = 0;

            foreach ($regionDef['cities'] as $slug => $cityDef) {
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
                $onMap = $status !== EstateCityConfig::STATUS_PLANNED;
                $isOpen = $status === EstateCityConfig::STATUS_OPEN;

                if ($onMap) {
                    $foundedInRegion++;
                }
                if ($isOpen) {
                    $openInRegion++;
                }

                $cities[] = [
                    'slug' => $slug,
                    'city_name' => $meta['city_name'],
                    'country_label' => $meta['country_label'],
                    'region_id' => $regionId,
                    'local' => $cityDef['local'],
                    'neighbors' => array_values($cityDef['neighbors'] ?? []),
                    'status' => $status,
                    'on_map' => $onMap,
                    'is_open' => $isOpen,
                    'plots_claimed' => $plotsClaimed,
                    'plots_total' => EstateCityConfig::TOTAL_PLOTS,
                    'user_plot_number' => $userPlot,
                    'is_home_city' => $homeEstate !== null && (string)$homeEstate['city_slug'] === $slug,
                ];
            }

            $regions[] = [
                'id' => $regionId,
                'label' => (string)$regionDef['label'],
                'world' => $regionDef['world'],
                'zone_polygon' => $regionDef['zone_polygon'],
                'neighbor_regions' => array_values($regionDef['neighbor_regions'] ?? []),
                'founded_count' => $foundedInRegion,
                'open_count' => $openInRegion,
                'city_count' => count($cities),
                'cities' => $cities,
            ];
        }

        $catalog = $this->treasuryCityService->getCatalog();

        return [
            'regions' => $regions,
            'founded_count' => $catalog['founded_count'],
            'open_count' => $catalog['open_count'],
            'total_cities' => count(EstateCityConfig::all()),
            'layout' => EstateWorldMapConfig::LAYOUT_PANGAEA_V1,
            'world_map' => [
                'aspect_ratio' => '16 / 9',
                'background' => 'image',
                'image' => '/mob_app/img/estate/pangaea_world.png',
            ],
            'battle_graph' => [
                'city_pairs' => EstateWorldMapConfig::allCityNeighborPairs(),
                'region_pairs' => EstateWorldMapConfig::allRegionNeighborPairs(),
                'cross_region_city_links' => EstateWorldMapConfig::crossRegionCityLinks(),
            ],
            'home_estate' => $homeEstate,
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

        $ownerNames = $this->resolvePlotOwnerNames($plotsByNumber);

        $myPlotNumber = 0;
        $homeEstate = $userId > 0 ? (new HomeEstateService($this->cityRepository))->ensureHomeEstate($userId) : null;
        $homePlotNumber = (int)($homeEstate['plot_number'] ?? 0);
        $homeCitySlug = (string)($homeEstate['city_slug'] ?? '');
        foreach ($plotsByNumber as $num => $rowData) {
            if ((int)($rowData['UF_OWNER_USER_ID'] ?? 0) === $userId) {
                $myPlotNumber = (int)$num;
                break;
            }
        }

        $myProjects = [];
        if ($userId > 0 && $myPlotNumber > 0) {
            $myProjects = (new EstatePlotService())->getPlotProjects($userId, $slug, $myPlotNumber);
        }

        $myStage = $myProjects !== []
            ? EstatePlotService::resolveVisualStageFromProjects($myProjects)
            : 'none';

        $plotStageCache = [];
        $plots = [];
        for ($num = 1; $num <= EstateCityConfig::TOTAL_PLOTS; $num++) {
            $plotRow = $plotsByNumber[$num] ?? null;
            $ownerId = (int)($plotRow['UF_OWNER_USER_ID'] ?? 0);
            $isMine = $userId > 0 && $ownerId === $userId;
            $estateStage = 'none';
            if ($ownerId > 0) {
                if ($isMine) {
                    $estateStage = $myStage;
                } else {
                    $cacheKey = $ownerId . ':' . $num;
                    if (!isset($plotStageCache[$cacheKey])) {
                        $ownerProjects = (new EstatePlotService())->getPlotProjects($ownerId, $slug, $num);
                        $plotStageCache[$cacheKey] = EstatePlotService::resolveVisualStageFromProjects($ownerProjects);
                    }
                    $estateStage = $plotStageCache[$cacheKey];
                }
            }
            $plots[] = [
                'number' => $num,
                'side' => EstateCityConfig::plotSide($num),
                'claimed' => $ownerId > 0,
                'is_mine' => $isMine,
                'is_home' => $isMine && $homePlotNumber > 0 && $homeCitySlug === $slug && $homePlotNumber === $num,
                'owner_user_id' => $ownerId,
                'owner_name' => $ownerId > 0 ? (string)($ownerNames[$ownerId] ?? ('user#' . $ownerId)) : '',
                'estate_stage' => $estateStage,
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

        $regionId = EstateWorldMapConfig::regionForSlug($slug);

        return [
            'slug' => $slug,
            'city_name' => $meta['city_name'],
            'country_label' => $meta['country_label'],
            'region_id' => $regionId,
            'region_label' => $regionId ? EstateWorldMapConfig::getRegionLabel($regionId) : '',
            'neighbors' => EstateWorldMapConfig::getCityNeighbors($slug),
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
            'my_plot_number' => $myPlotNumber,
            'my_estate_projects' => $myProjects,
            'home_estate' => $homeEstate,
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

    /**
     * @param array<int, array<string, mixed>> $plotsByNumber
     * @return array<int, string>
     */
    private function resolvePlotOwnerNames(array $plotsByNumber): array
    {
        $ids = [];
        foreach ($plotsByNumber as $row) {
            $ownerId = (int)($row['UF_OWNER_USER_ID'] ?? 0);
            if ($ownerId > 0) {
                $ids[$ownerId] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $names = [];
        $rows = UserTable::getList([
            'filter' => ['@ID' => array_keys($ids)],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
        ]);
        while ($row = $rows->fetch()) {
            $id = (int)($row['ID'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string)($row['NAME'] ?? '') . ' ' . (string)($row['LAST_NAME'] ?? ''));
            if ($name === '') {
                $name = (string)($row['LOGIN'] ?? ('user#' . $id));
            }
            $names[$id] = $name;
        }

        return $names;
    }
}
