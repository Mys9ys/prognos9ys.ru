<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\CityRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class EstatePlotService
{
    private const ESTATE_STAGES = ['estate_fence_1', 'estate_house_1'];

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
     *   city_slug:string,
     *   city_id:int,
     *   plot_number:int,
     *   certificate_left:int,
     *   projects:array<int, array<string, mixed>>
     * }
     */
    public function claimPlot(int $userId, string $citySlug, int $plotNumber): array
    {
        $citySlug = strtolower(trim($citySlug));
        if ($userId <= 0 || $citySlug === '' || !EstateCityConfig::isValidPlotNumber($plotNumber)) {
            throw new \InvalidArgumentException('Некорректные параметры участка');
        }

        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city) {
            throw new \RuntimeException('Город не основан');
        }

        if ((string)($city['UF_STATUS'] ?? '') !== EstateCityConfig::STATUS_OPEN) {
            throw new \RuntimeException('Участки доступны только в открытом городе');
        }

        $cityId = (int)($city['ID'] ?? 0);
        if ($cityId <= 0) {
            throw new \RuntimeException('Не найден город');
        }

        $myExisting = $this->cityRepository->getUserPlotInCity($cityId, $userId);
        if ($myExisting && (int)($myExisting['UF_PLOT_NUMBER'] ?? 0) !== $plotNumber) {
            throw new \RuntimeException('В этом городе уже есть ваша усадьба');
        }

        $certCount = $this->economyRepository->getEventAgnosticLootItemCount(
            $userId,
            'cert_estate',
            ChestLootConfig::CATEGORY_CERT
        );
        if ($certCount <= 0) {
            throw new \RuntimeException('Нет лицензии на усадьбу (cert_estate)');
        }

        $claimedNow = false;
        if (!$myExisting) {
            $this->cityRepository->claimPlot($cityId, $plotNumber, $userId);
            $claimedNow = true;
        }

        if ($claimedNow) {
            $this->economyRepository->decrementEventAgnosticLootItem(
                $userId,
                'cert_estate',
                ChestLootConfig::CATEGORY_CERT,
                1
            );
        }

        $homeService = new HomeEstateService($this->cityRepository);
        $homeBefore = $homeService->getHomeEstate($userId);
        $homeAutoSet = false;
        if ($claimedNow && $homeBefore === null) {
            $homeService->setHomeEstate($userId, $citySlug, $plotNumber);
            $homeAutoSet = true;
        }
        $homeAfter = $homeService->ensureHomeEstate($userId);

        foreach (self::ESTATE_STAGES as $code) {
            $this->professionRepository->ensureEstateConstructionProject($userId, $citySlug, $plotNumber, $code);
        }

        return [
            'city_slug' => $citySlug,
            'city_id' => $cityId,
            'plot_number' => $plotNumber,
            'claimed_now' => $claimedNow,
            'certificate_left' => $this->economyRepository->getEventAgnosticLootItemCount(
                $userId,
                'cert_estate',
                ChestLootConfig::CATEGORY_CERT
            ),
            'home_estate' => $homeAfter,
            'home_estate_auto_set' => $homeAutoSet,
            'home_estate_before' => $homeBefore,
            'projects' => $this->getPlotProjects($userId, $citySlug, $plotNumber),
        ];
    }

    /**
     * @return array{
     *   city_slug:string,
     *   plot_number:int,
     *   project_code:string,
     *   donated_qty:int,
     *   project:array<string,mixed>,
     *   projects:array<int,array<string,mixed>>
     * }
     */
    public function donateComponent(
        int $userId,
        string $citySlug,
        int $plotNumber,
        string $projectCode,
        string $componentCode,
        int $qty
    ): array {
        $citySlug = strtolower(trim($citySlug));
        $projectCode = trim($projectCode);
        $componentCode = trim($componentCode);
        $qty = (int)$qty;

        if (
            $userId <= 0
            || $citySlug === ''
            || $projectCode === ''
            || $componentCode === ''
            || $qty <= 0
            || !EstateCityConfig::isValidPlotNumber($plotNumber)
        ) {
            throw new \InvalidArgumentException('Некорректные параметры стройки');
        }

        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city || (string)($city['UF_STATUS'] ?? '') !== EstateCityConfig::STATUS_OPEN) {
            throw new \RuntimeException('Город недоступен для стройки');
        }

        $myPlot = $this->cityRepository->getUserPlotInCity((int)$city['ID'], $userId);
        if ((int)($myPlot['UF_PLOT_NUMBER'] ?? 0) !== $plotNumber) {
            throw new \RuntimeException('Этот участок вам не принадлежит');
        }

        if (!in_array($projectCode, self::ESTATE_STAGES, true)) {
            throw new \InvalidArgumentException('Неизвестный этап стройки усадьбы');
        }

        $project = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, $projectCode);
        if (!$project) {
            throw new \RuntimeException('Проект усадьбы не инициализирован');
        }

        if ($projectCode === 'estate_house_1') {
            $fence = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, 'estate_fence_1');
            if (!$fence || (string)($fence['UF_STATUS'] ?? '') !== 'complete') {
                throw new \RuntimeException('Сначала завершите строительство забора');
            }
        }

        if ((string)($project['UF_STATUS'] ?? '') === 'complete') {
            throw new \RuntimeException('Этот этап уже завершён');
        }

        if ((string)($project['UF_STATUS'] ?? '') === 'ready') {
            throw new \RuntimeException('Материалы собраны — нажмите «Построить»');
        }

        $recipe = EstateRecipesConfig::all()[$projectCode] ?? null;
        if (!$recipe) {
            throw new \RuntimeException('Рецепт этапа не найден');
        }

        $bom = (array)($recipe['components'] ?? []);
        if (!isset($bom[$componentCode])) {
            throw new \InvalidArgumentException('Компонент не входит в этап стройки');
        }

        $stash = $this->professionRepository->decodeStashJson((string)($project['UF_STASH_JSON'] ?? '{}'));
        $needLeft = (int)$bom[$componentCode] - (int)($stash[$componentCode] ?? 0);
        if ($needLeft <= 0) {
            throw new \RuntimeException('Этот компонент уже закрыт');
        }

        $donateQty = min($qty, $needLeft);
        $userQty = $this->professionRepository->getUserMaterialQty($userId, $componentCode);
        if ($userQty < $donateQty) {
            throw new \RuntimeException('Недостаточно компонентов в инвентаре');
        }

        $this->professionRepository->consumeUserMaterialQty($userId, $componentCode, $donateQty);
        $stash[$componentCode] = (int)($stash[$componentCode] ?? 0) + $donateQty;

        $this->persistProjectStash($project, $bom, $stash);

        return [
            'city_slug' => $citySlug,
            'plot_number' => $plotNumber,
            'project_code' => $projectCode,
            'donated_qty' => $donateQty,
            'project' => $this->formatProjectRow(
                $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, $projectCode) ?: $project,
                $userId
            ),
            'projects' => $this->getPlotProjects($userId, $citySlug, $plotNumber),
        ];
    }

    /**
     * Зачисление компонента по биржевому заказу — сразу в резерв стройки (stash).
     * Излишек сверх потребности этапа уходит в инвентарь.
     *
     * @return array{
     *   city_slug:string,
     *   plot_number:int,
     *   project_code:string,
     *   deposited_qty:int,
     *   inventory_qty:int,
     *   project:array<string,mixed>,
     *   projects:array<int,array<string,mixed>>
     * }
     */
    public function depositOrderFulfillment(
        int $userId,
        string $citySlug,
        int $plotNumber,
        string $projectCode,
        string $componentCode,
        int $qty
    ): array {
        $resolved = $this->resolveProjectComponentContext(
            $userId,
            $citySlug,
            $plotNumber,
            $projectCode,
            $componentCode,
            $qty
        );

        $stash = $resolved['stash'];
        $needLeft = $resolved['need_left'];
        $toStash = min($qty, $needLeft);
        $toInventory = $qty - $toStash;

        if ($toStash > 0) {
            $stash[$componentCode] = (int)($stash[$componentCode] ?? 0) + $toStash;
            $this->persistProjectStash($resolved['project'], $resolved['bom'], $stash);
        }

        if ($toInventory > 0) {
            $this->professionRepository->addUserMaterialQty($userId, $componentCode, $toInventory);
        }

        $project = $this->professionRepository->getEstateConstructionProject(
            $userId,
            $resolved['city_slug'],
            $resolved['plot_number'],
            $resolved['project_code']
        ) ?: $resolved['project'];

        return [
            'city_slug' => $resolved['city_slug'],
            'plot_number' => $resolved['plot_number'],
            'project_code' => $resolved['project_code'],
            'deposited_qty' => $toStash,
            'inventory_qty' => $toInventory,
            'project' => $this->formatProjectRow($project, $userId),
            'projects' => $this->getPlotProjects($userId, $resolved['city_slug'], $resolved['plot_number']),
        ];
    }

    /**
     * Вернуть компонент из резерва стройки в инвентарь.
     *
     * @return array{
     *   city_slug:string,
     *   plot_number:int,
     *   project_code:string,
     *   withdrawn_qty:int,
     *   project:array<string,mixed>,
     *   projects:array<int,array<string,mixed>>
     * }
     */
    public function withdrawComponentToInventory(
        int $userId,
        string $citySlug,
        int $plotNumber,
        string $projectCode,
        string $componentCode,
        int $qty
    ): array {
        $resolved = $this->resolveProjectComponentContext(
            $userId,
            $citySlug,
            $plotNumber,
            $projectCode,
            $componentCode,
            $qty
        );

        if ((string)($resolved['project']['UF_STATUS'] ?? '') === 'ready') {
            throw new \RuntimeException('Материалы собраны — нажмите «Построить»');
        }

        $stash = $resolved['stash'];
        $stashHave = (int)($stash[$componentCode] ?? 0);
        if ($stashHave <= 0) {
            throw new \RuntimeException('На стройке нет этого компонента');
        }

        $withdrawQty = min($qty, $stashHave);
        $stash[$componentCode] = $stashHave - $withdrawQty;
        if ($stash[$componentCode] <= 0) {
            unset($stash[$componentCode]);
        }

        $this->professionRepository->addUserMaterialQty($userId, $componentCode, $withdrawQty);
        $this->persistProjectStash($resolved['project'], $resolved['bom'], $stash);

        $project = $this->professionRepository->getEstateConstructionProject(
            $userId,
            $resolved['city_slug'],
            $resolved['plot_number'],
            $resolved['project_code']
        ) ?: $resolved['project'];

        return [
            'city_slug' => $resolved['city_slug'],
            'plot_number' => $resolved['plot_number'],
            'project_code' => $resolved['project_code'],
            'withdrawn_qty' => $withdrawQty,
            'project' => $this->formatProjectRow($project, $userId),
            'projects' => $this->getPlotProjects($userId, $resolved['city_slug'], $resolved['plot_number']),
        ];
    }

    /**
     * Завершить этап стройки: списать собранные материалы и отметить этап построенным.
     *
     * @return array{
     *   city_slug:string,
     *   plot_number:int,
     *   project_code:string,
     *   project:array<string,mixed>,
     *   projects:array<int,array<string,mixed>>
     * }
     */
    public function completeProjectBuild(
        int $userId,
        string $citySlug,
        int $plotNumber,
        string $projectCode
    ): array {
        $citySlug = strtolower(trim($citySlug));
        $projectCode = trim($projectCode);

        if (
            $userId <= 0
            || $citySlug === ''
            || $projectCode === ''
            || !EstateCityConfig::isValidPlotNumber($plotNumber)
        ) {
            throw new \InvalidArgumentException('Некорректные параметры стройки');
        }

        if (!in_array($projectCode, self::ESTATE_STAGES, true)) {
            throw new \InvalidArgumentException('Неизвестный этап стройки усадьбы');
        }

        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city || (string)($city['UF_STATUS'] ?? '') !== EstateCityConfig::STATUS_OPEN) {
            throw new \RuntimeException('Город недоступен для стройки');
        }

        $myPlot = $this->cityRepository->getUserPlotInCity((int)$city['ID'], $userId);
        if ((int)($myPlot['UF_PLOT_NUMBER'] ?? 0) !== $plotNumber) {
            throw new \RuntimeException('Этот участок вам не принадлежит');
        }

        $project = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, $projectCode);
        if (!$project) {
            throw new \RuntimeException('Проект усадьбы не инициализирован');
        }

        if ($projectCode === 'estate_house_1') {
            $fence = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, 'estate_fence_1');
            if (!$fence || (string)($fence['UF_STATUS'] ?? '') !== 'complete') {
                throw new \RuntimeException('Сначала постройте забор');
            }
        }

        $recipe = EstateRecipesConfig::all()[$projectCode] ?? null;
        if (!$recipe) {
            throw new \RuntimeException('Рецепт этапа не найден');
        }

        $bom = (array)($recipe['components'] ?? []);
        $stash = $this->professionRepository->decodeStashJson((string)($project['UF_STASH_JSON'] ?? '{}'));
        $remaining = $this->calcRemaining($bom, $stash);
        $status = (string)($project['UF_STATUS'] ?? 'building');

        if ($status === 'complete' && $remaining === [] && $stash === []) {
            throw new \RuntimeException('Этот этап уже построен');
        }

        if ($remaining !== []) {
            throw new \RuntimeException('Сначала соберите все материалы на стройке');
        }

        if ($status !== 'ready' && !($status === 'complete' && $stash !== [])) {
            throw new \RuntimeException('Этап ещё не готов к постройке');
        }

        $this->professionRepository->updateConstructionProject((int)$project['ID'], [
            'UF_STASH_JSON' => '{}',
            'UF_STATUS' => 'complete',
            'UF_PROGRESS' => 100,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        $project = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, $projectCode) ?: $project;

        return [
            'city_slug' => $citySlug,
            'plot_number' => $plotNumber,
            'project_code' => $projectCode,
            'project' => $this->formatProjectRow($project, $userId),
            'projects' => $this->getPlotProjects($userId, $citySlug, $plotNumber),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     */
    public static function resolveVisualStageFromProjects(array $projects): string
    {
        $fence = null;
        $house = null;

        foreach ($projects as $project) {
            $code = (string)($project['recipe_code'] ?? '');
            if ($code === 'estate_fence_1') {
                $fence = $project;
            } elseif ($code === 'estate_house_1') {
                $house = $project;
            }
        }

        if ($house && (string)($house['status'] ?? '') === 'complete') {
            return 'complete';
        }

        if ($fence && (string)($fence['status'] ?? '') === 'complete') {
            return 'house_building';
        }

        if ($fence && (string)($fence['status'] ?? '') === 'ready') {
            return 'fence_ready';
        }

        if ($fence || $house) {
            return 'fence_building';
        }

        return 'claimed';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlotProjects(int $userId, string $citySlug, int $plotNumber): array
    {
        $rows = [];
        foreach ($this->professionRepository->getEstateConstructionProjectsByPlot($userId, $citySlug, $plotNumber) as $row) {
            $rows[] = $this->formatProjectRow($row, $userId);
        }

        usort($rows, static function (array $a, array $b): int {
            $order = ['estate_fence_1' => 1, 'estate_house_1' => 2];
            return ($order[$a['recipe_code']] ?? 99) <=> ($order[$b['recipe_code']] ?? 99);
        });

        return $rows;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function formatProjectRow(array $project, int $userId = 0): array
    {
        $recipeCode = (string)($project['UF_RECIPE_CODE'] ?? '');
        $recipe = EstateRecipesConfig::all()[$recipeCode] ?? [];
        $bom = (array)($recipe['components'] ?? []);
        $stash = $this->professionRepository->decodeStashJson((string)($project['UF_STASH_JSON'] ?? '{}'));
        $status = (string)($project['UF_STATUS'] ?? 'building');
        $remaining = $this->calcRemaining($bom, $stash);

        if ($status === 'complete' && $remaining === [] && $stash !== []) {
            $status = 'ready';
        } elseif ($status !== 'complete' && $remaining === []) {
            $status = 'ready';
        }

        $canBuild = $status === 'ready';

        $inventory = [];
        foreach ($bom as $code => $_need) {
            $code = (string)$code;
            $inventory[$code] = $userId > 0
                ? $this->professionRepository->getUserMaterialQty($userId, $code)
                : 0;
        }

        $neededItems = EstateBuildingRecipeBridge::formatComponentList($bom);
        foreach ($neededItems as &$item) {
            $code = (string)($item['code'] ?? '');
            $item['user_have'] = (int)($inventory[$code] ?? 0);
            $item['stash_have'] = (int)($stash[$code] ?? 0);
        }
        unset($item);

        return [
            'project_id' => (int)($project['ID'] ?? 0),
            'recipe_code' => $recipeCode,
            'label' => (string)($recipe['label_ru'] ?? $recipe['label'] ?? $recipeCode),
            'status' => $status,
            'can_build' => $canBuild,
            'progress_pct' => $status === 'complete' ? 100 : $this->calcProgressPct($bom, $stash),
            'needed' => $bom,
            'remaining' => $remaining,
            'stash' => $stash,
            'inventory' => $inventory,
            'needed_items' => $neededItems,
            'nominal_total' => (float)($recipe['nominal_total'] ?? 0),
        ];
    }

    /**
     * @return array{
     *   city_slug:string,
     *   plot_number:int,
     *   project_code:string,
     *   component_code:string,
     *   project:array<string,mixed>,
     *   bom:array<string,int>,
     *   stash:array<string,int>,
     *   need_left:int
     * }
     */
    private function resolveProjectComponentContext(
        int $userId,
        string $citySlug,
        int $plotNumber,
        string $projectCode,
        string $componentCode,
        int $qty
    ): array {
        $citySlug = strtolower(trim($citySlug));
        $projectCode = trim($projectCode);
        $componentCode = trim($componentCode);
        $qty = (int)$qty;

        if (
            $userId <= 0
            || $citySlug === ''
            || $projectCode === ''
            || $componentCode === ''
            || $qty <= 0
            || !EstateCityConfig::isValidPlotNumber($plotNumber)
        ) {
            throw new \InvalidArgumentException('Некорректные параметры стройки');
        }

        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city || (string)($city['UF_STATUS'] ?? '') !== EstateCityConfig::STATUS_OPEN) {
            throw new \RuntimeException('Город недоступен для стройки');
        }

        $myPlot = $this->cityRepository->getUserPlotInCity((int)$city['ID'], $userId);
        if ((int)($myPlot['UF_PLOT_NUMBER'] ?? 0) !== $plotNumber) {
            throw new \RuntimeException('Этот участок вам не принадлежит');
        }

        if (!in_array($projectCode, self::ESTATE_STAGES, true)) {
            throw new \InvalidArgumentException('Неизвестный этап стройки усадьбы');
        }

        $project = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, $projectCode);
        if (!$project) {
            throw new \RuntimeException('Проект усадьбы не инициализирован');
        }

        if ($projectCode === 'estate_house_1') {
            $fence = $this->professionRepository->getEstateConstructionProject($userId, $citySlug, $plotNumber, 'estate_fence_1');
            if (!$fence || (string)($fence['UF_STATUS'] ?? '') !== 'complete') {
                throw new \RuntimeException('Сначала завершите строительство забора');
            }
        }

        $recipe = EstateRecipesConfig::all()[$projectCode] ?? null;
        if (!$recipe) {
            throw new \RuntimeException('Рецепт этапа не найден');
        }

        $bom = (array)($recipe['components'] ?? []);
        if (!isset($bom[$componentCode])) {
            throw new \InvalidArgumentException('Компонент не входит в этап стройки');
        }

        $stash = $this->professionRepository->decodeStashJson((string)($project['UF_STASH_JSON'] ?? '{}'));
        $needLeft = (int)$bom[$componentCode] - (int)($stash[$componentCode] ?? 0);

        return [
            'city_slug' => $citySlug,
            'plot_number' => $plotNumber,
            'project_code' => $projectCode,
            'component_code' => $componentCode,
            'project' => $project,
            'bom' => $bom,
            'stash' => $stash,
            'need_left' => max(0, $needLeft),
        ];
    }

    /**
     * @param array<string, mixed> $project
     * @param array<string, int> $bom
     * @param array<string, int> $stash
     */
    private function persistProjectStash(array $project, array $bom, array $stash): void
    {
        $remaining = $this->calcRemaining($bom, $stash);
        $status = $remaining === [] ? 'ready' : 'building';

        $this->professionRepository->updateConstructionProject((int)$project['ID'], [
            'UF_STASH_JSON' => $this->professionRepository->encodeStashJson($stash),
            'UF_STATUS' => $status,
            'UF_PROGRESS' => (int)round($this->calcProgressPct($bom, $stash)),
            'UF_UPDATED_AT' => new DateTime(),
        ]);
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
}

