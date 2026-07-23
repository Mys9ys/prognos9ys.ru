<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ChestOpenService
{
    public const POOL_WC26 = 'wc26';
    public const POOL_RPL = 'rpl';
    public const POOL_LEVEL = 'level';
    public const POOL_ACHIEVEMENT = 'achievement';
    public const POOL_PROFESSION = 'profession';

    private const MAX_OPEN_ALL = 30;

    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private GameEventScopeService $scopeService;
    private ChestOpenLogService $logService;
    private ProfessionRepository $professionRepository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = new WalletService($this->repository);
        $this->scopeService = new GameEventScopeService();
        $this->logService = new ChestOpenLogService($this->repository, $this->scopeService);
        $this->professionRepository = new ProfessionRepository();
    }

    public function openWc26Chests(int $userId, int $qty = 1): array
    {
        return $this->openChests($userId, self::POOL_WC26, $qty);
    }

    public function openRplChests(int $userId, int $qty = 1): array
    {
        return $this->openChests($userId, self::POOL_RPL, $qty);
    }

    public function openLevelChests(int $userId, int $qty = 1): array
    {
        return $this->openChests($userId, self::POOL_LEVEL, $qty);
    }

    public function openAchievementChests(int $userId, int $qty = 1): array
    {
        return $this->openChests($userId, self::POOL_ACHIEVEMENT, $qty);
    }

    public function openProfessionChests(int $userId, int $qty = 1): array
    {
        return $this->openChests($userId, self::POOL_PROFESSION, $qty);
    }

    /**
     * @return array{
     *   opens: array<int, array>,
     *   summary: array<string, mixed>,
     *   lines: array<int, array{text:string,status:string}>,
     * }
     */
    public function openChests(int $userId, string $pool, int $qty = 1): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $config = $this->resolvePoolConfig($pool);
        $eventId = (int)$config['consume_event_id'];
        if ($eventId <= 0) {
            throw new \RuntimeException($config['missing_event_error'] ?? 'Событие для пула сундуков не найдено');
        }

        $available = $this->repository->countOpenableWc26ChestUnits($userId, $eventId, $config['types']);
        if ($available <= 0) {
            throw new \RuntimeException($config['empty_error']);
        }

        $toOpen = max(1, min($available, $qty, self::MAX_OPEN_ALL));
        $opens = [];
        $summary = [
            'prognobaks' => 0.0,
            'rublius' => 0.0,
            'items' => [],
            'opened_count' => 0,
        ];

        for ($i = 0; $i < $toOpen; $i++) {
            $chest = $this->repository->consumeOneOpenableWc26ChestUnit($userId, $eventId, $config['types']);

            if (!$chest) {
                break;
            }

            $loot = $this->rollLootForConfig($config, (string)($chest['UF_TYPE'] ?? ''), $userId);
            $this->applyLoot($userId, $config['loot_event_id'], (int)$chest['ID'], $loot);
            $persistMeta = $this->logService->extractPersistFieldsFromChest($chest);
            $this->repository->addChestOpenLog(array_merge([
                'UF_USER_ID' => $userId,
                'UF_EVENT_ID' => $eventId,
                'UF_CHEST_ID' => (int)$chest['ID'],
                'UF_CHEST_TYPE' => (string)($chest['UF_TYPE'] ?? ''),
                'UF_LOOT_JSON' => json_encode($loot, JSON_UNESCAPED_UNICODE),
                'UF_CREATED_AT' => new DateTime(),
            ], $persistMeta));

            $opens[] = [
                'index' => $i + 1,
                'chest_type' => (string)($chest['UF_TYPE'] ?? ''),
                'blocks' => $loot,
                'lines' => $this->buildOpenLines($loot),
            ];

            $this->accumulateSummary($summary, $loot);
            $summary['opened_count']++;
        }

        if (!$opens) {
            throw new \RuntimeException('Не удалось открыть сундук');
        }

        return [
            'opens' => $opens,
            'summary' => $summary,
            'lines' => $this->buildSessionLines($opens, $summary),
            'pool' => $pool,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allPools(): array
    {
        return [
            self::POOL_WC26,
            self::POOL_RPL,
            self::POOL_LEVEL,
            self::POOL_ACHIEVEMENT,
            self::POOL_PROFESSION,
        ];
    }

    public function countOpenableChests(int $userId, string $pool): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $config = $this->resolvePoolConfig($pool);
        $eventId = (int)$config['consume_event_id'];
        if ($eventId <= 0) {
            return 0;
        }

        return $this->repository->countOpenableWc26ChestUnits($userId, $eventId, $config['types']);
    }

    public function countAllOpenableChests(int $userId): int
    {
        $total = 0;
        foreach (self::allPools() as $pool) {
            $total += $this->countOpenableChests($userId, $pool);
        }

        return $total;
    }

    /**
     * @return array{
     *   opens: array<int, array>,
     *   summary: array<string, mixed>,
     *   lines: array<int, array{text:string,status:string}>,
     *   pools: array<int, string>
     * }
     */
    public function openAllChests(int $userId, int $qtyPerPool = 999): array
    {
        $combinedOpens = [];
        $combinedSummary = [
            'prognobaks' => 0.0,
            'rublius' => 0.0,
            'items' => [],
            'opened_count' => 0,
        ];
        $combinedLines = [];
        $openedPools = [];

        foreach (self::allPools() as $pool) {
            try {
                $result = $this->openChests($userId, $pool, $qtyPerPool);
            } catch (\RuntimeException $e) {
                continue;
            }

            $opened = (int)($result['summary']['opened_count'] ?? 0);
            if ($opened <= 0) {
                continue;
            }

            $openedPools[] = $pool;
            $combinedOpens = array_merge($combinedOpens, $result['opens'] ?? []);
            $combinedSummary['prognobaks'] += (float)($result['summary']['prognobaks'] ?? 0);
            $combinedSummary['rublius'] += (float)($result['summary']['rublius'] ?? 0);
            $combinedSummary['opened_count'] += $opened;
            foreach ($result['summary']['items'] ?? [] as $itemKey => $itemQty) {
                $combinedSummary['items'][$itemKey] = ($combinedSummary['items'][$itemKey] ?? 0) + $itemQty;
            }
            foreach ($result['lines'] ?? [] as $line) {
                $combinedLines[] = $line;
            }
        }

        if ($combinedSummary['opened_count'] <= 0) {
            throw new \RuntimeException('Нет сундуков для открытия');
        }

        return [
            'opens' => $combinedOpens,
            'summary' => $combinedSummary,
            'lines' => $combinedLines,
            'pools' => $openedPools,
            'pool' => 'all',
        ];
    }

    /**
     * @return array{
     *   types:string[],
     *   loot_event_id:int,
     *   consume_event_id:int,
     *   generic_block3:bool,
     *   empty_error:string,
     *   missing_event_error?:string,
     *   profession_loot?:bool
     * }
     */
    private function resolvePoolConfig(string $pool): array
    {
        $anchorEventId = $this->scopeService->getAnchorEventId();

        if ($pool === self::POOL_WC26) {
            return [
                'types' => ChestLootConfig::WC26_OPENABLE_CHEST_TYPES,
                'loot_event_id' => $anchorEventId,
                'consume_event_id' => $anchorEventId,
                'generic_block3' => false,
                'empty_error' => 'Нет закрытых сундуков ЧМ-26',
                'missing_event_error' => 'Событие ЧМ-26 не найдено',
                'profession_loot' => false,
            ];
        }

        if ($pool === self::POOL_RPL) {
            $rplEventId = RplSeasonConfig::getEventId();

            return [
                'types' => ChestLootConfig::RPL_OPENABLE_CHEST_TYPES,
                'loot_event_id' => $rplEventId,
                'consume_event_id' => $rplEventId,
                'generic_block3' => false,
                'empty_error' => 'Нет закрытых сундуков РПЛ',
                'missing_event_error' => 'Событие РПЛ не найдено',
                'profession_loot' => false,
            ];
        }

        if ($pool === self::POOL_LEVEL) {
            return [
                'types' => [TreasureService::CHEST_TYPE_LEVEL],
                'loot_event_id' => ChestLootConfig::LOOT_EVENT_GLOBAL,
                'consume_event_id' => $anchorEventId,
                'generic_block3' => true,
                'empty_error' => 'Нет сундуков за уровень',
                'missing_event_error' => 'Событие ЧМ-26 не найдено',
                'profession_loot' => false,
            ];
        }

        if ($pool === self::POOL_ACHIEVEMENT) {
            return [
                'types' => [TreasureService::CHEST_TYPE_ACHIEVEMENT],
                'loot_event_id' => ChestLootConfig::LOOT_EVENT_GLOBAL,
                'consume_event_id' => $anchorEventId,
                'generic_block3' => true,
                'empty_error' => 'Нет сундуков за ачивки',
                'missing_event_error' => 'Событие ЧМ-26 не найдено',
                'profession_loot' => false,
            ];
        }

        if ($pool === self::POOL_PROFESSION) {
            return [
                'types' => [
                    TreasureService::CHEST_TYPE_PROFESSION,
                    TreasureService::CHEST_TYPE_PROFESSION_TIER_1,
                    TreasureService::CHEST_TYPE_PROFESSION_TIER_2,
                    TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
                ],
                'loot_event_id' => ChestLootConfig::LOOT_EVENT_GLOBAL,
                'consume_event_id' => $anchorEventId,
                'generic_block3' => false,
                'empty_error' => 'Нет сундуков профессий',
                'missing_event_error' => 'Событие ЧМ-26 не найдено',
                'profession_loot' => true,
            ];
        }

        throw new \InvalidArgumentException('Неизвестный пул сундуков');
    }

    /**
     * @param array{types:string[],loot_event_id:int,generic_block3:bool,empty_error:string,profession_loot?:bool} $config
     * @return array{block1:array|null,block2:array|null,block3:array|null}
     */
    private function rollLootForConfig(array $config, string $chestType, int $userId): array
    {
        if (!empty($config['profession_loot'])) {
            $tier = ChestLootConfig::resolveProfessionTierByChestType($chestType);
            $professionCodes = [];
            foreach ($this->professionRepository->getProfessionsByUserId($userId) as $row) {
                $code = trim((string)($row['UF_PROFESSION_CODE'] ?? ''));
                if ($code !== '') {
                    $professionCodes[] = $code;
                }
            }

            return ChestLootConfig::rollProfessionLoot($tier, array_values(array_unique($professionCodes)));
        }

        return $this->rollLoot((bool)$config['generic_block3']);
    }

    /**
     * @return array{block1:array|null,block2:array|null,block3:array|null}
     */
    private function rollLoot(bool $genericBlock3): array
    {
        $block1 = ChestLootConfig::rollFromTable(ChestLootConfig::getBlock1Table());

        $block2 = null;
        if (random_int(1, 100) <= ChestLootConfig::BLOCK2_CHANCE_PERCENT) {
            $block2 = ChestLootConfig::rollFromTable(ChestLootConfig::getBlock2Table());
        }

        $block3 = null;
        if (random_int(1, 100) <= ChestLootConfig::BLOCK3_CHANCE_PERCENT) {
            $block3Table = $genericBlock3
                ? ChestLootConfig::getGenericBlock3Table()
                : ChestLootConfig::getWc26Block3Table();
            $block3 = ChestLootConfig::rollFromTable($block3Table);
        }

        return [
            'block1' => $block1,
            'block2' => $block2,
            'block3' => $block3,
        ];
    }

    /**
     * @param array{block1:array|null,block2:array|null,block3:array|null} $loot
     */
    private function applyLoot(int $userId, int $eventId, int $chestId, array $loot): void
    {
        $block1 = $loot['block1'] ?? null;
        if (is_array($block1)) {
            if (($block1['kind'] ?? '') === 'currency') {
                $currency = (string)($block1['currency'] ?? '');
                $amount = round((float)($block1['amount'] ?? 0), 1);
                if ($amount > 0 && $currency !== '') {
                    $this->walletService->credit(
                        $userId,
                        $currency,
                        $amount,
                        'chest_open_loot',
                        'treasure_chest',
                        $chestId
                    );
                }
            } elseif (($block1['kind'] ?? '') === 'item') {
                $this->grantLootItem($userId, $eventId, $block1);
            }
        }

        foreach (['block2', 'block3'] as $key) {
            $block = $loot[$key] ?? null;
            if (!is_array($block) || ($block['kind'] ?? '') !== 'item') {
                continue;
            }
            $this->grantLootItem($userId, $eventId, $block);
        }
    }

    /**
     * @param array<string, mixed> $block
     */
    private function grantLootItem(int $userId, int $eventId, array $block): void
    {
        $code = (string)($block['code'] ?? '');
        if ($code === '') {
            return;
        }

        $category = (string)($block['category'] ?? ChestLootConfig::getItemCategory($code));
        $qty = max(1, (int)($block['qty'] ?? 1));
        if ($category === ChestLootConfig::CATEGORY_MATERIAL) {
            $this->professionRepository->addUserMaterialQty(
                $userId,
                $code,
                $qty,
                !empty($block['is_premium'])
            );

            return;
        }

        $sealed = $category === ChestLootConfig::CATEGORY_PACK ? 'Y' : 'N';
        $this->repository->incrementLootItem($userId, $eventId, $code, $category, $qty, $sealed);
    }

    /**
     * @param array{block1:array|null,block2:array|null,block3:array|null} $loot
     * @return array<int, array{text:string,status:string}>
     */
    private function buildOpenLines(array $loot): array
    {
        $lines = [];

        $block1 = $loot['block1'] ?? null;
        if (is_array($block1)) {
            $lines[] = ['text' => 'Блок 1: ' . ChestLootConfig::formatBlockLabel($block1), 'status' => 'ok'];
        }

        $block2 = $loot['block2'] ?? null;
        if (is_array($block2)) {
            $lines[] = ['text' => 'Блок 2: ' . ChestLootConfig::formatBlockLabel($block2), 'status' => 'ok'];
        } else {
            $lines[] = ['text' => 'Блок 2: пусто', 'status' => 'skip'];
        }

        $block3 = $loot['block3'] ?? null;
        if (is_array($block3)) {
            $lines[] = ['text' => 'Блок 3: ' . ChestLootConfig::formatBlockLabel($block3), 'status' => 'ok'];
        } else {
            $lines[] = ['text' => 'Блок 3: пусто', 'status' => 'skip'];
        }

        return $lines;
    }

    /**
     * @param array<int, array> $opens
     * @param array<string, mixed> $summary
     * @return array<int, array{text:string,status:string}>
     */
    private function buildSessionLines(array $opens, array $summary): array
    {
        $lines = [];

        foreach ($opens as $open) {
            $lines[] = [
                'text' => '—— Сундук ' . (int)($open['index'] ?? 0) . ' ——',
                'status' => 'ok',
            ];
            foreach ($open['lines'] ?? [] as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = ['text' => '════════ Итого ════════', 'status' => 'ok'];

        $prognobaks = round((float)($summary['prognobaks'] ?? 0), 1);
        $rublius = round((float)($summary['rublius'] ?? 0), 1);

        if ($prognobaks > 0) {
            $lines[] = ['text' => '+' . ChestLootConfig::formatPrognobaksAmount($prognobaks), 'status' => 'ok'];
        }
        if ($rublius > 0) {
            $lines[] = ['text' => '+' . ChestLootConfig::formatRubliusAmount($rublius), 'status' => 'ok'];
        }

        foreach (($summary['items'] ?? []) as $code => $count) {
            if ((int)$count <= 0) {
                continue;
            }
            $lines[] = [
                'text' => ChestLootConfig::formatSummaryItemLine((string)$code, (int)$count),
                'status' => 'ok',
            ];
        }

        $lines[] = [
            'text' => 'Открыто сундуков: ' . (int)($summary['opened_count'] ?? 0),
            'status' => 'ok',
        ];

        return $lines;
    }

    /**
     * @param array<string, mixed> $summary
     * @param array{block1:array|null,block2:array|null,block3:array|null} $loot
     */
    private function accumulateSummary(array &$summary, array $loot): void
    {
        $block1 = $loot['block1'] ?? null;
        if (is_array($block1)) {
            if (($block1['kind'] ?? '') === 'currency') {
                $currency = (string)($block1['currency'] ?? '');
                $amount = round((float)($block1['amount'] ?? 0), 1);
                if ($currency === GameEconomyConfig::CURRENCY_PROGNOBAKS) {
                    $summary['prognobaks'] = round((float)$summary['prognobaks'] + $amount, 1);
                } elseif ($currency === GameEconomyConfig::CURRENCY_RUBLIUS) {
                    $summary['rublius'] = round((float)$summary['rublius'] + $amount, 1);
                }
            } elseif (($block1['kind'] ?? '') === 'item') {
                $code = (string)($block1['code'] ?? '');
                $qty = max(1, (int)($block1['qty'] ?? 1));
                if ($code !== '') {
                    if (!isset($summary['items'][$code])) {
                        $summary['items'][$code] = 0;
                    }
                    $summary['items'][$code] += $qty;
                }
            }
        }

        foreach (['block2', 'block3'] as $key) {
            $block = $loot[$key] ?? null;
            if (!is_array($block)) {
                continue;
            }
            $code = (string)($block['code'] ?? '');
            if ($code === '') {
                continue;
            }
            if (!isset($summary['items'][$code])) {
                $summary['items'][$code] = 0;
            }
            $summary['items'][$code] += max(1, (int)($block['qty'] ?? 1));
        }
    }
}
