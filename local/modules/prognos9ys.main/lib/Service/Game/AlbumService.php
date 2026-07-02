<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\AlbumRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class AlbumService
{
    private AlbumRepository $albumRepository;
    private GameEconomyRepository $economyRepository;
    private ProfessionRepository $professionRepository;
    private GameEventScopeService $scopeService;

    /** @var int */
    private $contextUserId = 0;
    /** @var array<int, array<string, mixed>>|null */
    private $contextAlbumRows = null;
    /** @var array<int, array<int, array<string, mixed>>>|null */
    private $contextSlotsByAlbum = null;

    public function __construct(
        ?AlbumRepository $albumRepository = null,
        ?GameEconomyRepository $economyRepository = null,
        ?ProfessionRepository $professionRepository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->albumRepository = $albumRepository ?? new AlbumRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function getState(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->albumRepository->ensureSchema();
        $this->getUserAlbumRows($userId);

        $plankQty = $this->professionRepository->getUserMaterialQty($userId, 'plank', false);
        $clothQty = $this->professionRepository->getUserMaterialQty($userId, 'cloth', false);
        $this->economyRepository->ensureLearnedRecipesSchema();
        $recipeLearned = $this->economyRepository->hasLearnedRecipe($userId, AlbumConfig::RECIPE_ITEM_CODE);
        $recipeInInventory = $this->economyRepository->getEventAgnosticLootItemCount(
            $userId,
            AlbumConfig::RECIPE_ITEM_CODE,
            ChestLootConfig::CATEGORY_RECIPE
        );
        $canCraft = $recipeLearned
            && $plankQty >= AlbumConfig::RECIPE_PLANK
            && $clothQty >= AlbumConfig::RECIPE_CLOTH;

        $universalAlbums = $this->countUniversalAlbumItems($userId);
        $collectibles = $this->formatCollectibleInventory($userId);
        $activateInfo = $this->buildActivateInfo($userId);

        return [
            'recipe' => [
                'code' => AlbumConfig::RECIPE_ITEM_CODE,
                'label' => AlbumConfig::recipeLabel(),
                'learned' => $recipeLearned,
                'in_inventory' => $recipeInInventory,
            ],
            'craft' => [
                'plank_need' => AlbumConfig::RECIPE_PLANK,
                'cloth_need' => AlbumConfig::RECIPE_CLOTH,
                'plank_have' => $plankQty,
                'cloth_have' => $clothQty,
                'output_count' => AlbumConfig::CRAFT_OUTPUT_COUNT,
                'can_craft' => $canCraft,
                'needs_recipe' => !$recipeLearned,
            ],
            'universal_albums' => $universalAlbums,
            'activate' => $activateInfo,
            'glued_teams' => $this->getGluedTeamsByCollection($userId),
            'albums' => $this->formatAlbums($userId),
            'collectibles' => $collectibles,
            'mega' => $this->formatMegaProgress($userId),
        ];
    }

    /**
     * Лёгкий срез для game_info после мутаций (вклейка, активация).
     *
     * @return array{glued_teams:array<string,array<int,string>>,activate:array<string,mixed>,albums:array<int,array<string,mixed>>}
     */
    public function getProfileMeta(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'glued_teams' => [
                    AlbumConfig::COLLECTION_PENNANT_WC26 => [],
                    AlbumConfig::COLLECTION_SCARF_WC26 => [],
                ],
                'activate' => [
                    'allowed' => true,
                    'reason' => '',
                    'has_pennant' => false,
                    'has_scarf' => false,
                    'has_pending' => false,
                ],
                'albums' => [],
            ];
        }

        $this->albumRepository->ensureSchema();
        $albumRows = $this->getUserAlbumRows($userId);
        $albumIds = array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['ID'] ?? 0);
        }, $albumRows)));
        $slotsByAlbum = $this->albumRepository->getSlotsByAlbumIds($albumIds);
        $albums = [];

        foreach ($albumRows as $row) {
            $albumId = (int)($row['ID'] ?? 0);
            $gluedSlugs = [];
            foreach ($slotsByAlbum[$albumId] ?? [] as $slot) {
                $slug = (string)($slot['UF_TEAM_SLUG'] ?? '');
                if ($slug !== '') {
                    $gluedSlugs[] = $slug;
                }
            }

            $albums[] = [
                'id' => $albumId,
                'collection' => (string)($row['UF_COLLECTION'] ?? ''),
                'glued_slugs' => array_values(array_unique($gluedSlugs)),
                'glued_count' => count($gluedSlugs),
            ];
        }

        return [
            'glued_teams' => $this->buildGluedTeamsFromRows($albumRows),
            'activate' => $this->buildActivateInfoFromRows($albumRows),
            'albums' => $albums,
        ];
    }

    /**
     * @return array{album_id:int,lines:array<int, array{text:string,status:string}>}
     */
    public function activate(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        if ($this->countUniversalAlbumItems($userId) <= 0) {
            throw new \RuntimeException('Универсальный альбом не найден в инвентаре');
        }

        $activateInfo = $this->buildActivateInfo($userId);
        if (empty($activateInfo['allowed'])) {
            throw new \RuntimeException((string)($activateInfo['reason'] ?? 'Нельзя активировать ещё один альбом'));
        }

        $this->economyRepository->decrementEventAgnosticLootItem(
            $userId,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM,
            1
        );

        $eventId = $this->scopeService->getAnchorEventId();
        $albumId = $this->albumRepository->createAlbum($userId, $eventId);

        return [
            'album_id' => $albumId,
            'lines' => [
                ['text' => 'Альбом активирован — выберите первую вклейку', 'status' => 'ok'],
            ],
        ];
    }

    /**
     * Альбом для вклейки коллекции (точное совпадение или универсальный).
     */
    public function resolveAlbumIdForCollection(int $userId, string $collection): int
    {
        if ($userId <= 0 || $collection === '') {
            return 0;
        }

        $this->albumRepository->ensureSchema();
        $rows = $this->albumRepository->getAlbumsByUserId($userId);

        foreach ($rows as $row) {
            if ((string)($row['UF_COLLECTION'] ?? '') === $collection) {
                return (int)($row['ID'] ?? 0);
            }
        }

        foreach ($rows as $row) {
            $albumCollection = (string)($row['UF_COLLECTION'] ?? '');
            if ($albumCollection !== '' && $albumCollection !== AlbumConfig::COLLECTION_UNIVERSAL) {
                continue;
            }

            $required = $this->resolveRequiredCollectionForAlbum($userId, $row);
            if ($required !== null && $required !== $collection) {
                continue;
            }

            return (int)($row['ID'] ?? 0);
        }

        return 0;
    }

    /**
     * @return array{lines:array<int, array{text:string,status:string}>,album:array<string,mixed>}
     */
    public function glue(int $userId, int $albumId, string $itemCode): array
    {
        $this->resetContext();
        if ($userId <= 0 || $albumId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры');
        }

        $itemCode = trim($itemCode);
        $collection = AlbumConfig::collectionForItemCode($itemCode);
        if ($collection === null) {
            throw new \InvalidArgumentException('В альбом можно вклеить только вымпел или шарф ЧМ-26');
        }

        $teamSlug = Wc26CollectibleConfig::extractTeamSlugFromCollectibleCode($itemCode);
        if ($teamSlug === null) {
            throw new \InvalidArgumentException('Неизвестная сборная');
        }

        $album = $this->albumRepository->getAlbumById($albumId, $userId);
        if (!$album) {
            throw new \RuntimeException('Альбом не найден');
        }

        $albumCollection = (string)($album['UF_COLLECTION'] ?? '');
        if ($albumCollection !== '' && $albumCollection !== AlbumConfig::COLLECTION_UNIVERSAL && $albumCollection !== $collection) {
            throw new \RuntimeException('Этот альбом предназначен для другой коллекции');
        }

        if ($this->albumRepository->getSlotByAlbumAndTeam($albumId, $teamSlug)) {
            throw new \RuntimeException('Сборная уже вклеена в этот альбом');
        }

        if ($this->isTeamGluedInCollection($userId, $collection, $teamSlug)) {
            throw new \RuntimeException('Эта сборная уже вклеена в альбом коллекции');
        }

        $requiredCollection = $this->resolveRequiredCollectionForAlbum($userId, $album);
        if ($requiredCollection !== null && $collection !== $requiredCollection) {
            throw new \RuntimeException($this->requiredCollectionErrorMessage($requiredCollection));
        }

        $eventId = $this->economyRepository->findLootStackEventId(
            $userId,
            $itemCode,
            $collection === AlbumConfig::COLLECTION_PENNANT_WC26
                ? ChestLootConfig::CATEGORY_PENNANT
                : ChestLootConfig::CATEGORY_SCARF
        );
        if ($eventId === null) {
            throw new \RuntimeException('Предмет не найден в инвентаре');
        }

        if ($this->economyRepository->getLootItemCount($userId, $eventId, $itemCode, $collection === AlbumConfig::COLLECTION_PENNANT_WC26
            ? ChestLootConfig::CATEGORY_PENNANT
            : ChestLootConfig::CATEGORY_SCARF) <= 0) {
            throw new \RuntimeException('Предмет не найден в инвентаре');
        }

        $this->economyRepository->decrementLootItem($userId, $eventId, $itemCode, 1);

        if ($albumCollection === '' || $albumCollection === AlbumConfig::COLLECTION_UNIVERSAL) {
            $this->albumRepository->updateAlbum($albumId, [
                'UF_COLLECTION' => $collection,
            ]);
        }

        $this->albumRepository->addSlot($albumId, $teamSlug, $itemCode);

        $label = $collection === AlbumConfig::COLLECTION_PENNANT_WC26
            ? Wc26CollectibleConfig::getPennantLabel($itemCode)
            : Wc26CollectibleConfig::getScarfLabel($itemCode);

        return [
            'lines' => [
                ['text' => 'Вклеено: ' . $label, 'status' => 'ok'],
            ],
        ];
    }

    /**
     * Массовая вклейка всех подходящих оригиналов в активные альбомы.
     *
     * @return array{glued:int,lines:array<int, array{text:string,status:string}>}
     */
    public function glueAllEligible(int $userId, int $albumId = 0): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $lines = [];
        $glued = 0;
        $guard = 48;

        while ($guard-- > 0) {
            $this->resetContext();
            $collectibles = $this->formatCollectibleInventory($userId);
            if (!$collectibles) {
                break;
            }

            $matched = false;
            foreach ($this->getUserAlbumRows($userId) as $albumRow) {
                $currentAlbumId = (int)($albumRow['ID'] ?? 0);
                if ($albumId > 0 && $currentAlbumId !== $albumId) {
                    continue;
                }

                $albumCollection = (string)($albumRow['UF_COLLECTION'] ?? '');
                $gluedInAlbum = [];
                foreach ($this->getAlbumSlots($currentAlbumId) as $slotRow) {
                    $slug = (string)($slotRow['UF_TEAM_SLUG'] ?? '');
                    if ($slug !== '') {
                        $gluedInAlbum[$slug] = true;
                    }
                }

                foreach ($collectibles as $item) {
                    $itemCode = (string)($item['code'] ?? '');
                    $itemCollection = (string)($item['collection'] ?? '');
                    $teamSlug = (string)($item['team_slug'] ?? '');
                    if ($itemCode === '' || $teamSlug === '' || $itemCollection === '') {
                        continue;
                    }
                    if (isset($gluedInAlbum[$teamSlug])) {
                        continue;
                    }
                    if ($albumCollection !== ''
                        && $albumCollection !== AlbumConfig::COLLECTION_UNIVERSAL
                        && $albumCollection !== $itemCollection) {
                        continue;
                    }
                    if ($this->isTeamGluedInCollection($userId, $itemCollection, $teamSlug)) {
                        continue;
                    }

                    try {
                        $this->resetContext();
                        $result = $this->glue($userId, $currentAlbumId, $itemCode);
                        $line = $result['lines'][0] ?? ['text' => 'Вклеено', 'status' => 'ok'];
                        $lines[] = $line;
                        $glued++;
                        $matched = true;
                        break 2;
                    } catch (\Throwable $exception) {
                        continue;
                    }
                }
            }

            if (!$matched) {
                break;
            }
        }

        if ($glued <= 0) {
            $lines[] = ['text' => 'Нет подходящих вымпелов или шарфов для вклейки', 'status' => 'fail'];
        }

        return [
            'glued' => $glued,
            'lines' => $lines,
        ];
    }

    /**
     * Снять вклейку (админ/починка): вернуть предмет в инвентарь.
     *
     * @return array{lines:array<int, array{text:string,status:string}>}
     */
    public function unglueTeam(int $userId, int $albumId, string $teamSlug, bool $deleteAlbumIfEmpty = true): array
    {
        if ($userId <= 0 || $albumId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры');
        }

        $teamSlug = strtolower(trim($teamSlug));
        $album = $this->albumRepository->getAlbumById($albumId, $userId);
        if (!$album) {
            throw new \RuntimeException('Альбом не найден');
        }

        $slot = $this->albumRepository->getSlotByAlbumAndTeam($albumId, $teamSlug);
        if (!$slot) {
            throw new \RuntimeException('Вклейка не найдена');
        }

        $itemCode = (string)($slot['UF_ITEM_CODE'] ?? '');
        $collection = AlbumConfig::collectionForItemCode($itemCode);
        if ($collection === null) {
            throw new \RuntimeException('Неизвестный предмет вклейки');
        }

        $category = $collection === AlbumConfig::COLLECTION_PENNANT_WC26
            ? ChestLootConfig::CATEGORY_PENNANT
            : ChestLootConfig::CATEGORY_SCARF;
        $eventId = $this->scopeService->getAnchorEventId();
        if ($eventId <= 0) {
            throw new \RuntimeException('Событие ЧМ-26 не найдено');
        }

        $this->albumRepository->deleteSlot((int)$slot['ID']);

        $remaining = $this->albumRepository->countSlotsByAlbumId($albumId);
        if ($remaining <= 0) {
            if ($deleteAlbumIfEmpty) {
                $this->albumRepository->deleteAlbum($albumId);
            } else {
                $this->albumRepository->updateAlbum($albumId, [
                    'UF_COLLECTION' => AlbumConfig::COLLECTION_UNIVERSAL,
                ]);
            }
        }

        $this->economyRepository->incrementLootItem(
            $userId,
            $eventId,
            $itemCode,
            $category,
            1,
            'N'
        );

        $label = $collection === AlbumConfig::COLLECTION_PENNANT_WC26
            ? Wc26CollectibleConfig::getPennantLabel($itemCode)
            : Wc26CollectibleConfig::getScarfLabel($itemCode);

        return [
            'lines' => [
                ['text' => 'Снято из альбома: ' . $label, 'status' => 'ok'],
            ],
        ];
    }

    public function buildActivateInfo(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Некорректный пользователь',
                'has_pennant' => false,
                'has_scarf' => false,
                'has_pending' => false,
            ];
        }

        $this->albumRepository->ensureSchema();

        return $this->buildActivateInfoFromRows($this->getUserAlbumRows($userId));
    }

    /**
     * @param array<int, array<string, mixed>> $albumRows
     * @return array{allowed:bool,reason:string,has_pennant:bool,has_scarf:bool,has_pending:bool}
     */
    private function buildActivateInfoFromRows(array $albumRows): array
    {
        $hasPennant = false;
        $hasScarf = false;
        $hasPending = false;

        foreach ($albumRows as $album) {
            $collection = (string)($album['UF_COLLECTION'] ?? '');
            if ($collection === AlbumConfig::COLLECTION_PENNANT_WC26) {
                $hasPennant = true;
            } elseif ($collection === AlbumConfig::COLLECTION_SCARF_WC26) {
                $hasScarf = true;
            } else {
                $hasPending = true;
            }
        }

        if (count($albumRows) >= 2) {
            return [
                'allowed' => false,
                'reason' => 'Можно иметь не больше двух альбомов: вымпелы и шарфы ЧМ-26',
                'has_pennant' => $hasPennant,
                'has_scarf' => $hasScarf,
                'has_pending' => $hasPending,
            ];
        }

        if ($hasPending) {
            return [
                'allowed' => false,
                'reason' => 'Сначала сделайте первую вклейку в уже активированном альбоме',
                'has_pennant' => $hasPennant,
                'has_scarf' => $hasScarf,
                'has_pending' => $hasPending,
            ];
        }

        if ($hasPennant && $hasScarf) {
            return [
                'allowed' => false,
                'reason' => 'Уже есть альбомы для вымпелов и шарфов ЧМ-26',
                'has_pennant' => $hasPennant,
                'has_scarf' => $hasScarf,
                'has_pending' => $hasPending,
            ];
        }

        return [
            'allowed' => true,
            'reason' => '',
            'has_pennant' => $hasPennant,
            'has_scarf' => $hasScarf,
            'has_pending' => $hasPending,
        ];
    }

    public function getGluedTeamsByCollection(int $userId): array
    {
        if ($userId <= 0) {
            return [
                AlbumConfig::COLLECTION_PENNANT_WC26 => [],
                AlbumConfig::COLLECTION_SCARF_WC26 => [],
            ];
        }

        $this->albumRepository->ensureSchema();

        return $this->buildGluedTeamsFromRows($this->getUserAlbumRows($userId));
    }

    /**
     * @param array<int, array<string, mixed>> $albumRows
     * @return array<string, array<int, string>>
     */
    private function buildGluedTeamsFromRows(array $albumRows): array
    {
        $result = [
            AlbumConfig::COLLECTION_PENNANT_WC26 => [],
            AlbumConfig::COLLECTION_SCARF_WC26 => [],
        ];

        foreach ($albumRows as $album) {
            $collection = (string)($album['UF_COLLECTION'] ?? '');
            if ($collection !== AlbumConfig::COLLECTION_PENNANT_WC26
                && $collection !== AlbumConfig::COLLECTION_SCARF_WC26) {
                continue;
            }

            $albumId = (int)($album['ID'] ?? 0);
            foreach ($this->getAlbumSlots($albumId) as $slot) {
                $slug = (string)($slot['UF_TEAM_SLUG'] ?? '');
                if ($slug !== '') {
                    $result[$collection][] = $slug;
                }
            }
        }

        foreach ($result as $collection => $slugs) {
            $result[$collection] = array_values(array_unique($slugs));
        }

        return $result;
    }

    private function isTeamGluedInCollection(int $userId, string $collection, string $teamSlug): bool
    {
        $teamSlug = strtolower(trim($teamSlug));
        foreach ($this->albumRepository->getAlbumsByUserId($userId) as $album) {
            $albumCollection = (string)($album['UF_COLLECTION'] ?? '');
            if ($albumCollection !== ''
                && $albumCollection !== AlbumConfig::COLLECTION_UNIVERSAL
                && $albumCollection !== $collection) {
                continue;
            }

            if ($this->albumRepository->getSlotByAlbumAndTeam((int)$album['ID'], $teamSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $album
     */
    private function resolveRequiredCollectionForAlbum(int $userId, array $album): ?string
    {
        $albumCollection = (string)($album['UF_COLLECTION'] ?? '');
        if ($albumCollection === AlbumConfig::COLLECTION_PENNANT_WC26) {
            return AlbumConfig::COLLECTION_PENNANT_WC26;
        }
        if ($albumCollection === AlbumConfig::COLLECTION_SCARF_WC26) {
            return AlbumConfig::COLLECTION_SCARF_WC26;
        }

        $hasPennant = false;
        $hasScarf = false;
        foreach ($this->albumRepository->getAlbumsByUserId($userId) as $row) {
            $id = (int)($row['ID'] ?? 0);
            if ($id === (int)($album['ID'] ?? 0)) {
                continue;
            }
            $collection = (string)($row['UF_COLLECTION'] ?? '');
            if ($collection === AlbumConfig::COLLECTION_PENNANT_WC26) {
                $hasPennant = true;
            } elseif ($collection === AlbumConfig::COLLECTION_SCARF_WC26) {
                $hasScarf = true;
            }
        }

        if ($hasPennant && !$hasScarf) {
            return AlbumConfig::COLLECTION_SCARF_WC26;
        }
        if ($hasScarf && !$hasPennant) {
            return AlbumConfig::COLLECTION_PENNANT_WC26;
        }

        return null;
    }

    private function requiredCollectionErrorMessage(string $collection): string
    {
        if ($collection === AlbumConfig::COLLECTION_SCARF_WC26) {
            return 'Второй альбом — только для шарфов ЧМ-26';
        }

        return 'Второй альбом — только для вымпелов ЧМ-26';
    }

    private function countUniversalAlbumItems(int $userId): int
    {
        return $this->economyRepository->getEventAgnosticLootItemCount(
            $userId,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatAlbums(int $userId): array
    {
        $albums = [];
        foreach ($this->getUserAlbumRows($userId) as $row) {
            $albums[] = $this->formatSingleAlbum($row);
        }

        return $albums;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatSingleAlbum(array $row): array
    {
        $albumId = (int)($row['ID'] ?? 0);
        $collection = (string)($row['UF_COLLECTION'] ?? '');
        $gluedBySlug = [];

        foreach ($this->getAlbumSlots($albumId) as $slot) {
            $slug = (string)($slot['UF_TEAM_SLUG'] ?? '');
            $code = (string)($slot['UF_ITEM_CODE'] ?? '');
            $gluedBySlug[$slug] = [
                'team_slug' => $slug,
                'team_label' => Wc26CollectibleConfig::teamLabel($slug),
                'item_code' => $code,
                'item_label' => $collection === AlbumConfig::COLLECTION_SCARF_WC26
                    ? Wc26CollectibleConfig::getScarfLabel($code)
                    : Wc26CollectibleConfig::getPennantLabel($code),
                'glued' => true,
            ];
        }

        $gluedSlots = array_values($gluedBySlug);

        return [
            'id' => $albumId,
            'collection' => $collection,
            'collection_label' => AlbumConfig::collectionLabel($collection),
            'glued_count' => count($gluedBySlug),
            'slot_count' => AlbumConfig::SLOT_COUNT,
            'glued_slugs' => array_keys($gluedBySlug),
            'glued_slots' => $gluedSlots,
            'slots' => $gluedSlots,
        ];
    }

    /**
     * @return array<int, array{code:string,category:string,label:string,count:int,team_slug:string}>
     */
    private function formatCollectibleInventory(int $userId): array
    {
        $anchorEventId = $this->scopeService->getAnchorEventId();
        $stacks = ChestLootConfig::mergeInventoryLootStacks(array_merge(
            $this->economyRepository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0 ? $this->economyRepository->getLootItemStacksForUser($userId, $anchorEventId) : []
        ));

        $items = [];
        foreach ($stacks as $stack) {
            $code = (string)($stack['code'] ?? '');
            $category = (string)($stack['category'] ?? '');
            $count = (int)($stack['count'] ?? 0);
            if ($count <= 0 || !AlbumConfig::isSupportedCollectible($code)) {
                continue;
            }

            $slug = (string)(Wc26CollectibleConfig::extractTeamSlugFromCollectibleCode($code) ?? '');
            $items[] = [
                'code' => $code,
                'category' => $category,
                'label' => (string)($stack['label'] ?? $code),
                'count' => $count,
                'team_slug' => $slug,
                'collection' => AlbumConfig::collectionForItemCode($code),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{glued:int,thresholds:int[],next_threshold:?int}>
     */
    private function formatMegaProgress(int $userId): array
    {
        $claimMap = $this->economyRepository->getAchievementClaimMapForUser($userId);
        $catalog = AchievementConfig::getCatalog();
        $gluedByCollection = $this->buildGluedTeamsFromRows($this->getUserAlbumRows($userId));
        $result = [];

        foreach ([AlbumConfig::COLLECTION_PENNANT_WC26, AlbumConfig::COLLECTION_SCARF_WC26] as $collection) {
            $glued = count($gluedByCollection[$collection] ?? []);
            $next = null;
            foreach (AlbumConfig::MEGA_THRESHOLDS as $threshold) {
                if ($glued < $threshold) {
                    $next = $threshold;
                    break;
                }
            }

            $achievementCode = CollectionMegaAchievementConfig::achievementCodeForCollection($collection);
            $definition = $achievementCode ? ($catalog[$achievementCode] ?? null) : null;
            $claimedThreshold = $achievementCode
                ? (int)($claimMap[$achievementCode]['claimed_threshold'] ?? 0)
                : 0;

            $tiers = [];
            foreach ((array)($definition['levels'] ?? []) as $level) {
                $threshold = (int)($level['threshold'] ?? 0);
                if ($threshold <= 0) {
                    continue;
                }

                $tiers[] = [
                    'threshold' => $threshold,
                    'claimed' => $claimedThreshold >= $threshold,
                    'claimable' => $glued >= $threshold && $claimedThreshold < $threshold,
                    'reward' => $level['reward'] ?? [],
                ];
            }

            $result[$collection] = [
                'glued' => $glued,
                'thresholds' => AlbumConfig::MEGA_THRESHOLDS,
                'next_threshold' => $next,
                'achievement_code' => $achievementCode,
                'tiers' => $tiers,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getUserAlbumRows(int $userId): array
    {
        if ($this->contextUserId === $userId && $this->contextAlbumRows !== null) {
            return $this->contextAlbumRows;
        }

        $this->contextUserId = $userId;
        $this->contextAlbumRows = $this->albumRepository->getAlbumsByUserId($userId);
        $albumIds = array_map(static function (array $row): int {
            return (int)($row['ID'] ?? 0);
        }, $this->contextAlbumRows);
        $this->contextSlotsByAlbum = $this->albumRepository->getSlotsByAlbumIds($albumIds);

        return $this->contextAlbumRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAlbumSlots(int $albumId): array
    {
        if ($this->contextSlotsByAlbum === null) {
            return $this->albumRepository->getSlotsByAlbumId($albumId);
        }

        return $this->contextSlotsByAlbum[$albumId] ?? [];
    }

    private function resetContext(): void
    {
        $this->contextUserId = 0;
        $this->contextAlbumRows = null;
        $this->contextSlotsByAlbum = null;
    }
}
