<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class ChestOpenLogService
{
    private const DEFAULT_PAGE_SIZE = 25;

    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    /** @var array<int, array{round:int,number:int}> */
    private array $matchMetaCache = [];

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    /**
     * @param array<string, mixed> $chest
     * @return array{UF_MATCH_ID:int,UF_MATCH_NUMBER:int,UF_ROUND:int}
     */
    public function extractPersistFieldsFromChest(array $chest): array
    {
        $resolved = $this->resolveLogMeta([
            'UF_EVENT_ID' => (int)($chest['UF_EVENT_ID'] ?? 0),
            'UF_CHEST_ID' => (int)($chest['ID'] ?? 0),
            'UF_CHEST_TYPE' => (string)($chest['UF_TYPE'] ?? ''),
            'UF_MATCH_ID' => (int)($chest['UF_MATCH_ID'] ?? 0),
            'UF_MATCH_NUMBER' => 0,
            'UF_ROUND' => 0,
        ]);

        return [
            'UF_MATCH_ID' => (int)($chest['UF_MATCH_ID'] ?? 0),
            'UF_MATCH_NUMBER' => (int)($resolved['match_number'] ?? 0),
            'UF_ROUND' => (int)($resolved['round'] ?? 0),
        ];
    }

    public function getMetaForUser(int $userId): array
    {
        if ($userId <= 0) {
            return ['events' => [], 'total_opens' => 0];
        }

        $rows = $this->repository->getChestOpenLogRowsForUser($userId);
        if (!$rows) {
            return ['events' => [], 'total_opens' => 0];
        }

        $events = [];

        foreach ($rows as $row) {
            $resolved = $this->resolveLogMeta($row);
            $eventId = (int)($resolved['event_id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            if (!isset($events[$eventId])) {
                $events[$eventId] = [
                    'id' => $eventId,
                    'name' => $this->scopeService->getEventName($eventId) ?: ('Событие #' . $eventId),
                    'opens_count' => 0,
                    'groups' => [],
                ];
            }

            $events[$eventId]['opens_count']++;
            $groupKey = (string)($resolved['group_key'] ?? 'all');
            if (!isset($events[$eventId]['groups'][$groupKey])) {
                $events[$eventId]['groups'][$groupKey] = [
                    'key' => $groupKey,
                    'label' => (string)($resolved['group_label'] ?? $groupKey),
                    'count' => 0,
                ];
            }
            $events[$eventId]['groups'][$groupKey]['count']++;
        }

        $eventList = [];
        foreach ($events as $event) {
            $groups = array_values($event['groups']);
            usort($groups, static function (array $a, array $b): int {
                return self::compareGroupKeys((string)$a['key'], (string)$b['key']);
            });

            array_unshift($groups, [
                'key' => 'all',
                'label' => 'Все',
                'count' => (int)$event['opens_count'],
            ]);

            $event['groups'] = $groups;
            $eventList[] = $event;
        }

        usort($eventList, static function (array $a, array $b): int {
            return (int)($b['opens_count'] ?? 0) <=> (int)($a['opens_count'] ?? 0);
        });

        return [
            'events' => $eventList,
            'total_opens' => count($rows),
        ];
    }

    public function getEntries(
        int $userId,
        int $eventId = 0,
        string $groupKey = 'all',
        int $offset = 0,
        int $limit = self::DEFAULT_PAGE_SIZE
    ): array {
        if ($userId <= 0) {
            return [
                'entries' => [],
                'pagination' => [
                    'offset' => 0,
                    'limit' => 0,
                    'total' => 0,
                    'has_more' => false,
                ],
            ];
        }

        $page = $this->repository->getChestOpenLogPageForUser(
            $userId,
            $eventId,
            $groupKey,
            $offset,
            $limit
        );

        $entries = [];
        foreach ($page['items'] as $row) {
            $entries[] = $this->formatEntry($row);
        }

        $total = (int)($page['total'] ?? 0);
        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);

        return [
            'entries' => $entries,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + count($entries)) < $total,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatEntry(array $row): array
    {
        $resolved = $this->resolveLogMeta($row);
        $loot = json_decode((string)($row['UF_LOOT_JSON'] ?? ''), true);
        if (!is_array($loot)) {
            $loot = [];
        }

        $createdAt = $row['UF_CREATED_AT'] ?? null;
        $createdLabel = '';
        if ($createdAt instanceof DateTime) {
            $createdLabel = $createdAt->format('d.m.Y H:i');
        } elseif (is_string($createdAt) && $createdAt !== '') {
            $createdLabel = $createdAt;
        }

        $rewards = $this->buildRewardLabels($loot);

        return [
            'id' => (int)($row['ID'] ?? 0),
            'created_at' => $createdLabel,
            'event_id' => (int)($resolved['event_id'] ?? 0),
            'chest_type' => (string)($resolved['chest_type'] ?? ''),
            'chest_type_label' => (string)($resolved['chest_type_label'] ?? ''),
            'group_key' => (string)($resolved['group_key'] ?? 'all'),
            'group_label' => (string)($resolved['group_label'] ?? ''),
            'match_number' => (int)($resolved['match_number'] ?? 0),
            'rewards' => $rewards,
            'reward_line' => implode(', ', $rewards),
            'lines' => $this->buildLootLines($loot),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function resolveLogMeta(array $row): array
    {
        $eventId = (int)($row['UF_EVENT_ID'] ?? 0);
        $chestType = (string)($row['UF_CHEST_TYPE'] ?? '');
        $matchId = (int)($row['UF_MATCH_ID'] ?? 0);
        $matchNumber = (int)($row['UF_MATCH_NUMBER'] ?? 0);
        $round = (int)($row['UF_ROUND'] ?? 0);

        if ($matchId === 0 || ($round <= 0 && $matchNumber <= 0 && $chestType === TreasureService::CHEST_TYPE_MATCH)) {
            $chest = $this->repository->getTreasureChestById((int)($row['UF_CHEST_ID'] ?? 0));
            if ($chest) {
                if ($matchId <= 0) {
                    $matchId = (int)($chest['UF_MATCH_ID'] ?? 0);
                }
                if ($chestType === '') {
                    $chestType = (string)($chest['UF_TYPE'] ?? '');
                }
            }
        }

        if ($chestType === TreasureService::CHEST_TYPE_MATCH && $matchId > 0 && ($round <= 0 || $matchNumber <= 0)) {
            $matchMeta = $this->resolveMatchMeta($matchId);
            if ($round <= 0) {
                $round = (int)($matchMeta['round'] ?? 0);
            }
            if ($matchNumber <= 0) {
                $matchNumber = (int)($matchMeta['number'] ?? 0);
            }
        }

        return [
            'event_id' => $eventId,
            'chest_type' => $chestType,
            'chest_type_label' => $this->resolveChestTypeLabel($chestType),
            'match_number' => $matchNumber,
            'round' => $round,
            'group_key' => $this->resolveGroupKey($chestType, $round),
            'group_label' => $this->resolveGroupLabel($chestType, $round, $matchNumber),
        ];
    }

    private function resolveGroupKey(string $chestType, int $round): string
    {
        if ($chestType === TreasureService::CHEST_TYPE_MATCH && $round > 0) {
            return 'round_' . $round;
        }

        if (in_array($chestType, [
            TreasureService::CHEST_TYPE_ACHIEVEMENT,
            TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT,
            TreasureService::CHEST_TYPE_SHOP_WC26,
            TreasureService::CHEST_TYPE_MATCH,
        ], true)) {
            return $chestType;
        }

        return 'other';
    }

    private function resolveGroupLabel(string $chestType, int $round, int $matchNumber): string
    {
        if ($chestType === TreasureService::CHEST_TYPE_MATCH) {
            if ($round > 0) {
                return 'Тур ' . $round;
            }
            if ($matchNumber > 0) {
                return 'Матч #' . $matchNumber;
            }

            return 'Матчи';
        }

        if ($chestType === TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT) {
            return 'Ачивка ЧМ';
        }

        if ($chestType === TreasureService::CHEST_TYPE_ACHIEVEMENT) {
            return 'Ачивки';
        }

        if ($chestType === TreasureService::CHEST_TYPE_SHOP_WC26) {
            return 'Лавка';
        }

        return 'Прочее';
    }

    private function resolveChestTypeLabel(string $chestType): string
    {
        $map = [
            TreasureService::CHEST_TYPE_MATCH => 'Матч',
            TreasureService::CHEST_TYPE_ACHIEVEMENT => 'Ачивка',
            TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT => 'Ачивка ЧМ-26',
            TreasureService::CHEST_TYPE_SHOP_WC26 => 'Лавка',
        ];

        return $map[$chestType] ?? 'Сундук';
    }

    /**
     * @return array{round:int,number:int}
     */
    private function resolveMatchMeta(int $matchId): array
    {
        if ($matchId <= 0) {
            return ['round' => 0, 'number' => 0];
        }

        if (isset($this->matchMetaCache[$matchId])) {
            return $this->matchMetaCache[$matchId];
        }

        $meta = ['round' => 0, 'number' => 0];
        if (Loader::includeModule('iblock')) {
            $row = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => 2,
                    'ID' => $matchId,
                ],
                false,
                false,
                ['ID', 'PROPERTY_round', 'PROPERTY_number']
            )->GetNext();

            if ($row) {
                $meta = [
                    'round' => (int)($row['PROPERTY_ROUND_VALUE'] ?? 0),
                    'number' => (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0),
                ];
            }
        }

        $this->matchMetaCache[$matchId] = $meta;

        return $meta;
    }

    /**
     * @param array<string, mixed> $loot
     * @return array<int, string>
     */
    private function buildRewardLabels(array $loot): array
    {
        $labels = [];

        foreach (['block1', 'block2', 'block3'] as $key) {
            $block = $loot[$key] ?? null;
            if (!is_array($block)) {
                continue;
            }

            $label = ChestLootConfig::formatBlockLabel($block);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $loot
     * @return array<int, array{text:string,status:string}>
     */
    private function buildLootLines(array $loot): array
    {
        $lines = [];

        $block1 = $loot['block1'] ?? null;
        if (is_array($block1)) {
            $lines[] = ['text' => 'Блок 1: ' . ChestLootConfig::formatBlockLabel($block1), 'status' => 'ok'];
        }

        $block2 = $loot['block2'] ?? null;
        if (is_array($block2)) {
            $lines[] = ['text' => 'Блок 2: ' . ChestLootConfig::formatBlockLabel($block2), 'status' => 'ok'];
        }

        $block3 = $loot['block3'] ?? null;
        if (is_array($block3)) {
            $lines[] = ['text' => 'Блок 3: ' . ChestLootConfig::formatBlockLabel($block3), 'status' => 'ok'];
        }

        return $lines;
    }

    private static function compareGroupKeys(string $a, string $b): int
    {
        $weight = static function (string $key): int {
            if ($key === 'all') {
                return 0;
            }
            if (preg_match('/^round_(\d+)$/', $key, $matches)) {
                return 100 + (int)$matches[1];
            }
            if ($key === TreasureService::CHEST_TYPE_MATCH) {
                return 90;
            }
            if ($key === TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT) {
                return 205;
            }
            if ($key === TreasureService::CHEST_TYPE_ACHIEVEMENT) {
                return 200;
            }
            if ($key === TreasureService::CHEST_TYPE_SHOP_WC26) {
                return 210;
            }

            return 300;
        };

        $diff = $weight($a) <=> $weight($b);

        return $diff !== 0 ? $diff : strcmp($a, $b);
    }
}
