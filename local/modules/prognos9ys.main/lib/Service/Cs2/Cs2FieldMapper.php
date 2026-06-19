<?php

namespace Prognos9ys\Main\Service\Cs2;

use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

class Cs2FieldMapper
{
    private Cs2IblockRegistry $registry;

    public function __construct(?Cs2IblockRegistry $registry = null)
    {
        $this->registry = $registry ?? new Cs2IblockRegistry();
    }

    /**
     * @param array<string|int, mixed> $fields
     * @return array<int, mixed>
     */
    public function prognosisToBitrix(array $fields, ?string $mapScoresJson = null): array
    {
        return $this->mapFields(Cs2IblockRegistry::IBLOCK_PROGNOSIS, $fields, $mapScoresJson);
    }

    /**
     * @param array<string|int, mixed> $fields
     * @return array<int, mixed>
     */
    public function matchResultToBitrix(array $fields, ?string $mapScoresJson = null): array
    {
        return $this->mapFields(Cs2IblockRegistry::IBLOCK_MATCHES, $fields, $mapScoresJson);
    }

    /**
     * @param array<string, mixed> $scores
     * @return array<int, mixed>
     */
    public function resultScoresToBitrix(array $scores): array
    {
        $mapped = [];
        foreach ($scores as $code => $value) {
            $propId = $this->registry->getPropertyId(Cs2IblockRegistry::IBLOCK_RESULT, (string)$code);
            if ($propId > 0) {
                $mapped[$propId] = $value;
            }
        }

        return $mapped;
    }

    /**
     * @param array<string|int, mixed> $fields
     * @return array<int, mixed>
     */
    private function mapFields(string $iblockCode, array $fields, ?string $mapScoresJson): array
    {
        $aliases = [
            'goal_home' => 'maps_home',
            'goal_guest' => 'maps_guest',
            'domination' => 'opening_pct',
            'corner' => 'pistol_pct',
            'yellow' => 'clutches_home',
            'red' => 'clutches_guest',
            'offside' => 'map_scores',
            15 => 'maps_home',
            16 => 'maps_guest',
            18 => 'result',
            19 => 'diff',
            28 => 'sum',
            32 => 'opening_pct',
            20 => 'pistol_pct',
            21 => 'clutches_home',
            22 => 'clutches_guest',
            29 => 'map_scores',
            30 => 'number',
            17 => 'match_id',
            31 => 'user_id',
            52 => 'events',
            7 => 'maps_home',
            8 => 'maps_guest',
            9 => 'result',
            10 => 'opening_pct',
            11 => 'pistol_pct',
            12 => 'clutches_home',
            13 => 'clutches_guest',
            25 => 'diff',
            26 => 'sum',
        ];

        $normalized = [];
        foreach ($fields as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $code = $aliases[$key] ?? (string)$key;
            $normalized[$code] = $value;
        }

        if ($mapScoresJson !== null && $mapScoresJson !== '') {
            $normalized['map_scores'] = $mapScoresJson;
        }

        $mapped = [];
        foreach ($normalized as $code => $value) {
            $propId = $this->registry->getPropertyId($iblockCode, (string)$code);
            if ($propId > 0) {
                $mapped[$propId] = $value;
            }
        }

        return $mapped;
    }
}
